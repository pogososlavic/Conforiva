<?php

/** Read the actual local presenter in three locales, without SQL. */

use App\Services\Catalogue;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->isLocal() || config('catalogue.source') !== 'files' || config('dashboard.enabled')) {
    throw new RuntimeException('LOCAL_READ_ONLY_FILES_CONTEXT_REQUIRED');
}
$report = ['verified_at_utc' => gmdate('c'), 'sql_written' => false, 'locales' => []];
foreach (['fr', 'en', 'de'] as $locale) {
    app()->setLocale($locale);
    $catalogue = new Catalogue;
    $products = $catalogue->products();
    $missing = $products->filter(fn ($product) => empty($product['image']))->pluck('slug')->values()->all();
    if ($products->count() !== 3709 || $missing || $catalogue->sourceUnavailable() || $catalogue->translationUnavailable()) {
        throw new RuntimeException('PHOTO_COVERAGE_INCOMPLETE');
    }
    $report['locales'][$locale] = ['products' => $products->count(), 'with_photo' => $products->count(), 'without_photo' => $missing];
}
file_put_contents(storage_path('app/private/product-photo-alternatives-20261019/presenter-verification.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_THROW_ON_ERROR).PHP_EOL;
