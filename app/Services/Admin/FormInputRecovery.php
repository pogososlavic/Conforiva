<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/** Bounded display-only projections. Never put these private texts in a session. */
final class FormInputRecovery
{
    public function revision(Request $request, int $minimum = 0): ?int
    {
        return $this->integer($request->input('revision'), $minimum, PHP_INT_MAX);
    }

    private function integer(mixed $value, int $minimum, int $maximum): ?int
    {
        if (! is_int($value) && ! is_string($value)) {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum, 'max_range' => $maximum]]);

        return $number === false ? null : $number;
    }

    private function text(array $input, string $key, int $limit, mixed $fallback = ''): mixed
    {
        if (! Arr::has($input, $key)) {
            return $fallback;
        }
        $value = Arr::get($input, $key);

        return $value === null ? '' : (is_string($value) ? mb_substr($value, 0, $limit) : $fallback);
    }

    private function flag(array $input, string $key): bool
    {
        return in_array($input[$key] ?? null, [true, 1, '1'], true);
    }

    public function product(Request $request, array $product): array
    {
        $input = $request->all();
        foreach (['price' => 20, 'brand' => 200, 'category' => 200] as $key => $limit) {
            $product[$key] = $this->text($input, $key, $limit, $product[$key] ?? '');
        }
        if (empty($product['slug'])) {
            $product['reference'] = $this->text($input, 'reference', 255, $product['reference']);
        }
        $product['quantity'] = $this->integer($input['quantity'] ?? null, 0, 10_000_000) ?? $product['quantity'];
        foreach (['condition' => ['new', 'neuf', 'used', 'occasion', 'refurbished', 'reconditionne', ''],
            'universe' => ['firewood', 'heating', 'appliances', 'furniture', 'televisions', 'other']] as $key => $allowed) {
            if (in_array($input[$key] ?? null, $allowed, true)) {
                $product[$key] = $input[$key];
            }
        }
        $product['condition'] = ['new' => 'neuf', 'used' => 'occasion', 'refurbished' => 'reconditionne'][$product['condition'] ?? '']
            ?? ($product['condition'] ?? '');
        $product['published'] = $this->flag($input, 'published');
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (['name' => 255, 'description' => 20000, 'category' => 200] as $key => $limit) {
                $product['languages'][$locale][$key] = $this->text($input, 'languages.'.$locale.'.'.$key, $limit,
                    $product['languages'][$locale][$key] ?? '');
            }
        }
        foreach (['gtin' => ['gtin', 14], 'mpn' => ['mpn', 70], 'manufacturer_source_url' => ['source_url', 1000]] as $key => [$field, $limit]) {
            $product['manufacturer'][$field] = $this->text($input, $key, $limit, $product['manufacturer'][$field] ?? '');
        }
        $product['manufacturer']['verified'] = $this->flag($input, 'manufacturer_verified');
        $product['recovery_remove_images'] = [];
        if (is_array($input['remove_images'] ?? null)) {
            foreach (array_slice($input['remove_images'], 0, 20) as $value) {
                $index = $this->integer($value, 0, 19);
                if ($index !== null) {
                    $product['recovery_remove_images'][] = $index;
                }
            }
        }
        // A missing or stale revision never becomes the newly read server revision.
        $revision = $this->revision($request);
        $product['recovery_revision_valid'] = $revision !== null && $revision === (int) $product['revision'];
        $product['revision'] = $revision ?? '';

        return $product;
    }

    public function document(Request $request, array $data): array
    {
        $input = $request->all();
        foreach (['name' => 150, 'company' => 200, 'email' => 254, 'address' => 300, 'postal_code' => 20,
            'city' => 100, 'country' => 2, 'siren' => 9, 'vat' => 17] as $key => $limit) {
            $data['customer'][$key] = $this->text($input, 'customer.'.$key, $limit, $data['customer'][$key]);
        }
        foreach (['delivery_address' => 500, 'buyer_reference' => 100, 'tax_note' => 2000,
            'valid_until' => 10, 'operation_date' => 10, 'due_date' => 10, 'payment_terms' => 2000,
            'delivery_terms' => 2000, 'discount_terms' => 2000, 'late_terms' => 2000, 'legal_notes' => 2000] as $key => $limit) {
            $data[$key] = $this->text($input, $key, $limit, $data[$key]);
        }
        foreach (['document_language' => ['fr', 'en', 'de'], 'tax_mode' => ['', 'taxed', 'exempt', 'reverse']] as $key => $allowed) {
            if (in_array($input[$key] ?? null, $allowed, true)) {
                $data[$key] = $input[$key];
            }
        }
        foreach (['professional', 'eco_reviewed', 'guarantee_reviewed'] as $key) {
            $data[$key] = $this->flag($input, $key);
        }
        if (is_array($input['lines'] ?? null)) {
            if (! empty($data['order_id'])) {
                // Committed order identity, quantities and prices remain authoritative.
                foreach ($data['lines'] as $index => &$line) {
                    foreach (['vat_rate' => 5, 'eco' => 9] as $key => $limit) {
                        $line[$key] = $this->text($input, 'lines.'.$index.'.'.$key, $limit, $line[$key]);
                    }
                }
                unset($line);
            } else {
                $lines = [];
                foreach (array_slice(array_values($input['lines']), 0, 50) as $line) {
                    if (! is_array($line)) {
                        continue;
                    }
                    $shown = [];
                    foreach (['reference' => 255, 'name' => 600, 'price' => 9, 'vat_rate' => 5, 'eco' => 9] as $key => $limit) {
                        $shown[$key] = $this->text($line, $key, $limit);
                    }
                    $shown['quantity'] = $this->integer($line['quantity'] ?? null, 1, 10000) ?? 1;
                    $lines[] = $shown;
                }
                $data['lines'] = $lines;
            }
        }

        return $data;
    }
}
