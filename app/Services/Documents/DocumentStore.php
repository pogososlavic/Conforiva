<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Local, single-instance storage. Issuance commits the number, snapshot and PDF together. */
final class DocumentStore
{
    private function root(): string
    {
        $root = storage_path('app/private/commercial-documents');
        if (is_link($root)) {
            throw new RuntimeException('Unsafe document directory.');
        }
        File::ensureDirectoryExists($root, 0700, true);

        return $root;
    }

    private function locked(callable $callback, bool $write = false): mixed
    {
        $path = $this->root().'/.lock';
        if (is_link($path)) {
            throw new RuntimeException('Unsafe lock.');
        }
        $handle = fopen($path, 'c');
        if (! $handle) {
            throw new RuntimeException('Cannot open document lock.');
        }
        chmod($path, 0600);
        try {
            if (! flock($handle, $write ? LOCK_EX : LOCK_SH)) {
                throw new RuntimeException('Cannot lock documents.');
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function read(string $path): array
    {
        if (is_link($path) || ! is_file($path) || filesize($path) > 20000000) {
            throw new RuntimeException('Invalid document archive.');
        }

        return json_decode(Crypt::decryptString(file_get_contents($path)), true, 64, JSON_THROW_ON_ERROR);
    }

    private function commit(string $path, array $record): void
    {
        if (file_exists($path) || is_link($path)) {
            throw new RuntimeException('Document archive already exists.');
        }
        $temp = $this->root().'/.pending-'.Str::uuid();
        $handle = fopen($temp, 'x');
        if (! $handle) {
            throw new RuntimeException('Cannot create document archive.');
        }
        try {
            chmod($temp, 0600);
            $bytes = Crypt::encryptString(json_encode($record, JSON_THROW_ON_ERROR));
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = fwrite($handle, substr($bytes, $offset));
                if (! $written) {
                    throw new RuntimeException('Cannot write document archive.');
                }
                $offset += $written;
            }
            fflush($handle);
            if (function_exists('fsync')) {
                fsync($handle);
            }
            fclose($handle);
            $handle = null;
            if (! rename($temp, $path)) {
                throw new RuntimeException('Cannot commit document archive.');
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    private function load(string $id): array
    {
        abort_unless(Str::isUuid($id), 404);
        $issued = $this->root().'/'.$id.'-issued.encrypted';
        if (is_file($issued)) {
            return $this->read($issued);
        }
        $files = glob($this->root().'/'.$id.'-draft-*.encrypted') ?: [];
        rsort($files, SORT_STRING);
        abort_unless($files !== [], 404);

        return $this->read($files[0]);
    }

    public function find(string $id): array
    {
        return $this->locked(fn () => $this->load($id));
    }

    private function records(): array
    {
        $records = [];
        foreach (glob($this->root().'/*.encrypted') ?: [] as $file) {
            if (! preg_match('/\A([a-f0-9-]{36})-(?:issued|draft-\d{8})\.encrypted\z/', basename($file), $match)) {
                throw new RuntimeException('Unrecognized document archive.');
            }
            // Do not ignore corrupt issued records: doing so could silently reuse an invoice number.
            $record = $this->read($file);
            if (isset($records[$match[1]]) && ($records[$match[1]]['status'] === 'issued' || $records[$match[1]]['revision'] > $record['revision'])) {
                continue;
            }
            $records[$match[1]] = $record;
        }

        return array_values($records);
    }

    public function all(): array
    {
        return $this->locked(function () {
            $records = $this->records();
            usort($records, fn ($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
            foreach ($records as &$record) {
                unset($record['pdf']);
            }

            return $records;
        });
    }

    public function forOrder(string $orderId, bool $issuedOnly = true): array
    {
        return array_values(array_filter($this->all(), fn ($record) => $record['data']['order_id'] === $orderId && (! $issuedOnly || $record['status'] === 'issued')));
    }

    public function create(string $type, ?string $orderId = null, ?array $copy = null, ?array $source = null, string $origin = 'admin'): array
    {
        abort_unless(in_array($origin, ['admin', 'automatic_order_quote'], true), 422);
        $data = $copy ?? app(DocumentData::class)->blank($type, $orderId);
        abort_unless(isset(DocumentData::TYPES[$type]), 404);
        $data['type'] = $type;
        $data = app(DocumentData::class)->normalize($data, $data);

        return $this->locked(function () use ($data, $source, $origin) {
            $record = ['id' => (string) Str::uuid(), 'revision' => 1, 'status' => 'draft', 'number' => null, 'origin' => $origin, 'source_document' => $source, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(), 'data' => $data, 'history' => [['action' => 'created', 'at' => now()->toIso8601String()]]];
            $this->commit($this->root().'/'.$record['id'].'-draft-00000001.encrypted', $record);

            return $record;
        }, true);
    }

    public function save(string $id, int $revision, array $input): array
    {
        return $this->locked(function () use ($id, $revision, $input) {
            $record = $this->load($id);
            $this->editable($record, $revision);
            $record['data'] = app(DocumentData::class)->normalize($input, $record['data']);
            $record['revision']++;
            $record['updated_at'] = now()->toIso8601String();
            $record['history'][] = ['action' => 'saved', 'at' => $record['updated_at']];
            $this->commit($this->root().'/'.$id.'-draft-'.str_pad((string) $record['revision'], 8, '0', STR_PAD_LEFT).'.encrypted', $record);

            return $record;
        }, true);
    }

    private function editable(array $record, int $revision): void
    {
        if ($record['status'] !== 'draft' || $record['revision'] !== $revision) {
            throw ValidationException::withMessages(['document' => 'Ce document a changé ou a déjà été émis. Rechargez la page ; un document émis ne peut être modifié.']);
        }
    }

    private function series(array $records): ?array
    {
        $series = null;
        foreach ($records as $record) {
            if ($record['status'] !== 'issued') {
                continue;
            }
            $sequence = ($record['series']['scope'] ?? null) === config('documents.numbering.scope')
                ? ($record['series']['sequence'] ?? null) : null;
            if ($sequence === null && preg_match('/\AHOMYA\/(?:FR|EN|DE)-([0-9]{3,})\z/', (string) ($record['number'] ?? ''), $match) === 1) {
                $sequence = (int) $match[1];
            }
            if (is_numeric($sequence) && (! $series || (int) $sequence > (int) $series['sequence'])) {
                $series = [
                    'scope' => config('documents.numbering.scope'),
                    'prefix' => config('documents.numbering.prefix'),
                    'sequence' => (int) $sequence,
                    'language' => $record['data']['document_language'] ?? 'fr',
                    'type' => $record['data']['type'],
                ];
            }
        }

        return $series;
    }

    public function currentSeries(string $type): ?array
    {
        abort_unless(isset(DocumentData::TYPES[$type]), 404);

        return $this->locked(fn () => $this->series($this->records()));
    }

    public function issue(string $id, int $revision, array $numbering, ?callable $renderer = null): array
    {
        return $this->locked(function () use ($id, $revision, $numbering, $renderer) {
            $record = $this->load($id);
            if ($record['status'] === 'issued') {
                return $record; // Retry returns the exact committed package; never allocates a second number.
            }
            $this->editable($record, $revision);
            $data = app(DocumentData::class)->normalize($record['data'], $record['data']);
            $language = $numbering['document_language'] ?? $data['document_language'] ?? 'fr';
            if (! isset(DocumentData::LANGUAGES[$language])) {
                throw ValidationException::withMessages(['document_language' => 'Choisissez une langue disponible pour le document.']);
            }
            $data['document_language'] = $language;
            $allowMissingBankDetails = ($record['origin'] ?? null) === 'automatic_order_quote';
            $missing = app(DocumentData::class)->missing($data, $allowMissingBankDetails);
            if ($missing) {
                throw ValidationException::withMessages(['document' => $missing]);
            }
            $records = $this->records();
            if ($data['type'] === 'invoice' && $data['order_id']) {
                foreach ($records as $other) {
                    if ($other['status'] === 'issued' && $other['data']['type'] === 'invoice' && $other['data']['order_id'] === $data['order_id']) {
                        throw ValidationException::withMessages(['document' => 'Une facture existe déjà pour cette commande. Aucune seconde facture ne sera émise.']);
                    }
                }
            }
            $series = $this->series($records);
            if ($series) {
                $series['sequence']++;
            } else {
                $prefix = (string) config('documents.numbering.prefix');
                $first = (int) config('documents.numbering.first_sequence');
                if ($prefix !== 'HOMYA' || empty($numbering['series_confirmed']) || (int) ($numbering['first_sequence'] ?? 0) !== $first) {
                    throw ValidationException::withMessages(['numbering' => 'La série globale HOMYA doit commencer au numéro confirmé et ne peut être modifiée dans un brouillon.']);
                }
                $series = ['scope' => config('documents.numbering.scope'), 'prefix' => $prefix, 'sequence' => $first, 'confirmed_at' => now()->toIso8601String()];
            }
            if ($series['sequence'] > 99999999) {
                throw ValidationException::withMessages(['numbering' => 'La série est épuisée ; une nouvelle règle doit être définie.']);
            }
            $language = (string) $data['document_language'];
            $languageCode = config('documents.numbering.language_codes.'.$language);
            if (! is_string($languageCode) || ! preg_match('/\A[A-Z]{2}\z/', $languageCode)) {
                throw ValidationException::withMessages(['document_language' => 'La langue sélectionnée ne possède pas de code de numérotation.']);
            }
            $digits = max(3, (int) config('documents.numbering.minimum_digits', 3));
            $number = $series['prefix'].'/'.$languageCode.'-'.str_pad((string) $series['sequence'], $digits, '0', STR_PAD_LEFT);
            foreach ($records as $other) {
                if (($other['number'] ?? null) === $number) {
                    throw ValidationException::withMessages(['numbering' => 'Ce numéro existe déjà dans les archives HOMYA.']);
                }
            }
            $record['data'] = $data;
            $record['issuer'] = app(DocumentData::class)->issuer();
            $record['status'] = 'issued';
            $record['number'] = $number;
            $series['language'] = $language;
            $series['type'] = $data['type'];
            $record['series'] = $series;
            $record['issued_at'] = now('Europe/Paris')->toIso8601String();
            $record['updated_at'] = $record['issued_at'];
            $record['history'][] = ['action' => 'issued', 'at' => $record['issued_at'], 'actor' => config('admin.username'), 'review_confirmed' => true];
            $pdf = $renderer ? $renderer($record) : app(DocumentPdf::class)->render($record);
            if (! is_string($pdf) || ! str_starts_with($pdf, '%PDF-') || strlen($pdf) > 10000000) {
                throw new RuntimeException('Invalid generated PDF.');
            }
            $record['pdf'] = base64_encode($pdf);
            $record['pdf_sha256'] = hash('sha256', $pdf);
            $this->commit($this->root().'/'.$id.'-issued.encrypted', $record);

            return $record;
        }, true);
    }

    public function pdf(array $record, ?string $previewLanguage = null): string
    {
        if ($record['status'] === 'draft') {
            if (in_array($record['data']['type'], ['quote', 'purchase_order'], true)) {
                $record['data']['bank_details'] = app(BankTransfer::class)->documentDetails();
            }
            if ($previewLanguage !== null) {
                if (! isset(DocumentData::LANGUAGES[$previewLanguage])) {
                    throw ValidationException::withMessages(['language' => 'Choisissez une langue disponible pour le document.']);
                }
                $record['data']['document_language'] = $previewLanguage;
            }
            $record['issuer'] = app(DocumentData::class)->issuer();

            return app(DocumentPdf::class)->render($record);
        }
        $pdf = base64_decode($record['pdf'], true);
        if (! is_string($pdf) || ! hash_equals($record['pdf_sha256'], hash('sha256', $pdf))) {
            throw new RuntimeException('Document integrity failure.');
        }

        return $pdf;
    }
}
