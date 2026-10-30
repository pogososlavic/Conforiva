<?php

namespace App\Services\Documents;

use App\Services\PrivateLocalFiles;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

final class DocumentPdf
{
    public function render(array $record): string
    {
        $language = $record['data']['document_language'] ?? 'fr';
        if (! isset(DocumentData::LANGUAGES[$language])) {
            $language = 'fr';
        }
        File::ensureDirectoryExists(storage_path(), 0700, true);
        $directory = PrivateLocalFiles::path(storage_path(), 'app/private/commercial-pdf', false);
        File::ensureDirectoryExists($directory, 0700, true);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', $directory);
        $options->set('fontCache', $directory);
        $options->set('chroot', [public_path('branding'), base_path('vendor/dompdf'), $directory]);
        $pdf = new Dompdf($options);
        $logo = public_path('branding/home-confort-logo-v1.png');
        $logoData = is_file($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : null;
        $pdf->loadHtml(view('documents.pdf', ['record' => $record, 'data' => $record['data'], 'issuer' => $record['issuer'], 'logo' => $logoData, 'language' => $language])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $pdf->getCanvas()->page_text(29, 815, 'HOME CONFORT · '.__('documents.types.'.$record['data']['type'], [], $language).' · '.($record['number'] ?? __('documents.draft', [], $language)), $font, 7, [0.39, 0.43, 0.36]);
        $pdf->getCanvas()->page_text(527, 815, '{PAGE_NUM} / {PAGE_COUNT}', $font, 7, [0.39, 0.43, 0.36]);

        return $pdf->output();
    }
}
