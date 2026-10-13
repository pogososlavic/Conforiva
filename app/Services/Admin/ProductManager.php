<?php

namespace App\Services\Admin;

use App\Services\Catalogue;
use App\Services\Commerce\OrderStore;
use App\Services\MerchantProducts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductManager
{
    private ?array $rows = null;

    public function rows(): array
    {
        if (! config('dashboard.enabled')) {
            return [];
        }

        return $this->rows ??= app(OrderStore::class)->connection()->table('commerce_admin_products')->get()->keyBy('slug')->all();
    }

    public function decorate(array $product): array
    {
        $row = $this->rows()[$product['slug']] ?? null;
        if (! $row) {
            return $product;
        }
        $content = json_decode($row->content, true, 32, JSON_THROW_ON_ERROR);
        if (($content['admin_tombstone'] ?? false) || ($content['admin_removed'] ?? false)) {
            $product['admin_published'] = false;

            return $product;
        }
        $locale = app()->getLocale();
        $text = $content['languages'][$locale] ?? null;
        if ($text !== null) {
            $product['name'] = $text['name'];
            $product['description'] = $text['description'];
            $product['description_hidden'] = false;
        }
        $product['price_cents'] = $row->price_cents;
        $product['promotion'] = null;
        $product['brand'] = $content['brand'];
        $product['universe'] = $content['universe'];
        if (isset($content['condition'])) {
            $product['condition'] = $content['condition'];
        }
        $product['manufacturer'] = $content['manufacturer'] ?? self::emptyManufacturer();
        $product['category'] = ['slug' => $content['category']['slug'], 'name' => $text['category'] ?? ($product['category']['name'] ?? $content['category']['name'])];
        $product['admin_published'] = (bool) $row->published;
        if (array_key_exists('images', $content) && $content['images'] === []) {
            $product['image'] = null;
            $product['images'] = [];
        }
        if (isset($content['image'])) {
            $product['image'] = $content['image'];
            $product['images'] = $content['images'] ?? [$content['image']];
        }

        return $product;
    }

    public function custom(): array
    {
        $items = [];
        foreach ($this->rows() as $row) {
            $content = json_decode($row->content, true, 32, JSON_THROW_ON_ERROR);
            if (! ($content['custom'] ?? false)) {
                continue;
            }
            $text = $content['languages'][app()->getLocale()] ?? null;
            // Do not invent a translation for a newly created product.
            if (! $text || ! $row->published) {
                continue;
            }
            $items[] = $this->decorate(['slug' => $row->slug, 'reference' => $row->reference, 'name' => $text['name'], 'description' => $text['description'], 'price_cents' => $row->price_cents, 'currency' => 'EUR', 'universe' => $content['universe'], 'has_variants' => false, 'variants' => [], 'issues' => [], 'condition' => null, 'specifications' => [], 'description_hidden' => false, 'image' => null, 'images' => []]);
        }

        return $items;
    }

    public function customForAdmin(string $slug): ?array
    {
        $row = $this->rows()[$slug] ?? null;
        if (! $row) {
            return null;
        }
        $content = json_decode($row->content, true, 32, JSON_THROW_ON_ERROR);
        if (! ($content['custom'] ?? false)) {
            return null;
        }
        $text = $content['languages'][app()->getLocale()] ?? $content['languages']['fr'] ?? null;
        $image = $content['image'] ?? ($content['images'][0] ?? null);
        $url = is_array($image) ? ($image['local_url'] ?? '') : '';
        $path = is_string($url) ? public_path(ltrim($url, '/')) : '';
        if (! is_string($url) || ! preg_match('~\A/media/admin-products/[A-Za-z0-9]{40}\.webp\z~D', $url)
            || ! is_file($path) || is_link($path)) {
            $image = null;
        }

        return ['slug' => $row->slug, 'reference' => $row->reference,
            'name' => $text['name'] ?? $row->slug, 'description' => $text['description'] ?? '',
            'price_cents' => $row->price_cents, 'currency' => 'EUR', 'universe' => $content['universe'] ?? 'other',
            'has_variants' => false, 'variants' => [], 'issues' => [], 'condition' => $content['condition'] ?? null,
            'category' => ['slug' => $content['category']['slug'] ?? 'autre', 'name' => $text['category'] ?? ($content['category']['name'] ?? 'Autre')],
            'brand' => $content['brand'] ?? '', 'description_hidden' => false,
            'manufacturer' => $content['manufacturer'] ?? self::emptyManufacturer(),
            'image' => $image, 'images' => $image ? [$image] : [], 'admin_published' => (bool) $row->published];
    }

    public function form(?string $slug): array
    {
        $db = app(OrderStore::class)->connection();
        if ($slug === null) {
            return ['slug' => '', 'reference' => '', 'revision' => 0, 'price' => '', 'quantity' => 0, 'published' => false, 'brand' => '', 'category' => '', 'manufacturer' => self::emptyManufacturer(), 'has_variants' => false, 'languages' => ['fr' => ['name' => '', 'description' => ''], 'en' => ['name' => '', 'description' => ''], 'de' => ['name' => '', 'description' => '']]];
        }
        $row = $db->table('commerce_admin_products')->where('slug', $slug)->first();
        $inventory = $db->table('commerce_inventory')->where('slug', $slug)->first();
        abort_unless($inventory, 404);
        $languages = [];
        $originalLocale = app()->getLocale();
        $base = null;
        try {
            foreach (['fr', 'en', 'de'] as $locale) {
                app()->setLocale($locale);
                $p = app(Catalogue::class)->productForAdmin($slug);
                if ($locale === 'fr') {
                    $base = $p;
                }
                if ($p) {
                    $languages[$locale] = ['name' => $p['name'], 'description' => $p['description'] ?? '', 'category' => $p['category']['name']];
                }
            }
        } finally {
            app()->setLocale($originalLocale);
        }
        $content = $row ? json_decode($row->content, true, 32, JSON_THROW_ON_ERROR) : [];

        return ['slug' => $slug, 'reference' => $inventory->reference, 'revision' => $row->revision ?? 0, 'price' => number_format(($inventory->approved_price_cents ?? 0) / 100, 2, '.', ''), 'quantity' => $inventory->quantity, 'reserved' => $inventory->reserved, 'published' => $row ? (bool) $row->published : (bool) ($base['admin_published'] ?? true), 'brand' => $content['brand'] ?? ($base['brand'] ?? ''), 'category' => $content['category']['name'] ?? ($base['category']['name'] ?? ''), 'languages' => $content['languages'] ?? $languages, 'image' => $content['image'] ?? ($base['image'] ?? null), 'universe' => $content['universe'] ?? ($base['universe'] ?? 'other'), 'images' => $content['images'] ?? ($base['images'] ?? []), 'condition' => $content['condition'] ?? ($base['condition'] ?? null), 'manufacturer' => $content['manufacturer'] ?? self::emptyManufacturer(), 'has_variants' => (bool) ($base['has_variants'] ?? false)];
    }

    public static function manufacturerRules(): array
    {
        return [
            'gtin' => ['nullable', 'string', 'max:14', function ($attribute, $value, $fail) {
                if (! MerchantProducts::validGtin($value)) {
                    $fail('Le GTIN doit comporter 8, 12, 13 ou 14 chiffres avec une clé de contrôle valide.');
                }
            }],
            'mpn' => ['nullable', 'string', 'max:70', 'not_regex:/[\p{Cc}]/u'],
            'manufacturer_source_url' => ['nullable', 'string', 'url:https', 'max:1000', 'not_regex:/[\p{Cc}\s]/u'],
            'manufacturer_verified' => ['nullable', 'boolean'],
        ];
    }

    private static function emptyManufacturer(): array
    {
        return ['gtin' => null, 'mpn' => null, 'source_url' => null, 'verified' => false];
    }

    private function manufacturer(array $data, array $previous): array
    {
        $fields = array_intersect_key($data, self::manufacturerRules());
        if ($fields === []) {
            // Older editor requests must not erase independently supplied manufacturer evidence.
            return $previous['manufacturer'] ?? self::emptyManufacturer();
        }
        $values = Validator::make($fields, self::manufacturerRules())->validate();
        $mpn = trim($values['mpn'] ?? '');
        $manufacturer = ['gtin' => ($values['gtin'] ?? '') ?: null,
            'mpn' => $mpn === '' ? null : $mpn,
            'source_url' => ($values['manufacturer_source_url'] ?? '') ?: null,
            'verified' => (bool) ($values['manufacturer_verified'] ?? false)];
        if ($manufacturer['source_url'] !== null
            && (parse_url($manufacturer['source_url'], PHP_URL_USER) !== null
                || parse_url($manufacturer['source_url'], PHP_URL_PASS) !== null)) {
            throw ValidationException::withMessages(['manufacturer_source_url' => 'La source fabricant ne doit pas contenir d’identifiants de connexion.']);
        }
        if ($manufacturer['verified'] && $manufacturer['source_url'] === null) {
            throw ValidationException::withMessages(['manufacturer_source_url' => 'Ajoutez le lien du fabricant ou du fournisseur qui confirme ces identifiants.']);
        }
        if ($manufacturer['verified'] && ! MerchantProducts::validGtin($manufacturer['gtin'])
            && ($manufacturer['mpn'] === null || trim($data['brand'] ?? '') === '')) {
            throw ValidationException::withMessages(['manufacturer_verified' => 'Confirmez un GTIN valide ou un MPN fabricant associé à la marque avant de déclarer ces données vérifiées.']);
        }

        return $manufacturer;
    }

    public function save(?string $slug, array $data, $image = null, array $extraImages = []): string
    {
        return Cache::lock('admin-product-write', 30)->block(5, function () use ($slug, $data, $image, $extraImages) {
            $db = app(OrderStore::class)->connection();
            $new = $slug === null;
            $slug ??= 'homya-'.Str::lower((string) Str::ulid());
            $existing = $db->table('commerce_admin_products')->where('slug', $slug)->first();
            $previous = $existing ? json_decode($existing->content, true, 32, JSON_THROW_ON_ERROR) : [];
            $languages = [];
            foreach (['fr', 'en', 'de'] as $locale) {
                if (trim($data['languages'][$locale]['name'] ?? '') !== '') {
                    $languages[$locale] = ['name' => trim($data['languages'][$locale]['name']), 'description' => trim($data['languages'][$locale]['description'] ?? ''), 'category' => trim($data['languages'][$locale]['category'] ?? $data['category'])];
                }
            }
            $content = ['custom' => $previous['custom'] ?? $new, 'universe' => $previous['universe'] ?? $data['universe'], 'languages' => $languages, 'condition' => $data['condition'] ?? ($previous['condition'] ?? null), 'brand' => $data['brand'], 'category' => ['slug' => Str::slug($data['category']), 'name' => $data['category']]];
            $content['manufacturer'] = $this->manufacturer($data, $previous);
            $gallery = $previous['images'] ?? (isset($previous['image']) ? [$previous['image']] : []);
            if (! $existing && ! $new) {
                $gallery = app(Catalogue::class)->product($slug)['images'] ?? [];
            }
            $gallery = array_values(array_filter($gallery, fn ($photo, $index) => ! in_array($index, array_map('intval', $data['remove_images'] ?? []), true), ARRAY_FILTER_USE_BOTH));
            $written = [];
            $price = Catalogue::cents($data['price']);
            try {
                if ($image) {
                    $photo = $this->image($image);
                    $written[] = $photo['local_url'];
                    if ($gallery) {
                        $gallery[0] = $photo;
                    } else {
                        $gallery[] = $photo;
                    }
                }
                foreach ($extraImages as $upload) {
                    $photo = $this->image($upload);
                    $written[] = $photo['local_url'];
                    $gallery[] = $photo;
                }
                if (count($gallery) > 20) {
                    throw ValidationException::withMessages(['images' => 'La galerie peut contenir 20 photos au maximum. Retirez des photos avant d’en ajouter.']);
                }
                $content['images'] = $gallery;
                if ($gallery) {
                    $content['image'] = $gallery[0];
                }
                $db->transaction(function () use ($db, $slug, $data, $content, $price, $new) {
                    $inventory = $db->table('commerce_inventory')->where('slug', $slug)->lockForUpdate()->first();
                    $row = $db->table('commerce_admin_products')->where('slug', $slug)->lockForUpdate()->first();
                    if (($row->revision ?? 0) !== (int) $data['revision']) {
                        throw ValidationException::withMessages(['revision' => 'Ce produit a été modifié. Rechargez sa fiche avant de continuer.']);
                    }
                    if (! $new && ! $inventory) {
                        abort(404);
                    }
                    if ($data['quantity'] < ($inventory->reserved ?? 0)) {
                        throw ValidationException::withMessages(['quantity' => 'Le stock total ne peut pas être inférieur au stock réservé.']);
                    }
                    if ($new && $db->table('commerce_inventory')->where('reference', $data['reference'])->exists()) {
                        throw ValidationException::withMessages(['reference' => 'Cette référence est déjà utilisée.']);
                    }
                    $reference = $inventory->reference ?? $data['reference'];
                    $published = (bool) ($data['published'] ?? false);
                    if ($content['custom'] && $published && empty($content['condition'])) {
                        throw ValidationException::withMessages(['condition' => 'Précisez l’état réel du produit avant publication.']);
                    }
                    if ($content['custom'] && $published && ! isset($content['image'])) {
                        throw ValidationException::withMessages(['image' => 'Ajoutez une photo du produit avant publication.']);
                    }
                    $values = ['reference' => $reference, 'content' => json_encode($content, JSON_THROW_ON_ERROR), 'price_cents' => $price, 'published' => $published, 'revision' => ($row->revision ?? 0) + 1, 'updated_at' => now()];
                    if (! $row) {
                        $values['created_at'] = now();
                    }
                    $db->table('commerce_admin_products')->updateOrInsert(['slug' => $slug], $values);
                    $stock = ['reference' => $reference, 'quantity' => $data['quantity'], 'approved_price_cents' => $price, 'sale_enabled' => $published, 'validated_at' => now(), 'updated_at' => now()];
                    if (! $inventory) {
                        $stock += ['reserved' => 0, 'created_at' => now()];
                    }
                    $db->table('commerce_inventory')->updateOrInsert(['slug' => $slug], $stock);
                    app(Activity::class)->record('product_saved', $slug, $db, $slug.'|'.$values['revision']);
                });
            } catch (\Throwable $error) {
                foreach ($written as $url) {
                    File::delete(public_path(ltrim($url, '/')));
                }
                throw $error;
            }
            $this->rows = null;

            return $slug;
        });
    }

    public function togglePublication(string $slug): bool
    {
        $catalogue = app(Catalogue::class);
        $product = $catalogue->productForAdmin($slug);
        abort_unless($product, 404);
        $db = app(OrderStore::class)->connection();

        return Cache::lock('admin-product-write', 30)->block(5, function () use ($db, $slug, $product) {
            return $db->transaction(function () use ($db, $slug, $product) {
                $inventory = $db->table('commerce_inventory')->where('slug', $slug)->lockForUpdate()->first();
                abort_unless($inventory, 404);
                $row = $db->table('commerce_admin_products')->where('slug', $slug)->lockForUpdate()->first();
                $currentlyPublished = (bool) ($row->published ?? ($product['admin_published'] ?? true));
                $published = ! $currentlyPublished;
                $content = $row ? json_decode($row->content, true, 32, JSON_THROW_ON_ERROR) : $this->baseContent($product);
                if ($published && ($content['admin_tombstone'] ?? false)) {
                    // A visibility-only marker must not turn a catalogue promotion into a permanent price override.
                    $db->table('commerce_admin_products')->where('slug', $slug)->delete();
                    app(Activity::class)->record('product_restored', $slug, $db, $slug.'|visibility|'.Str::uuid());

                    return true;
                }
                if (! $published && ! $row && ! ($content['custom'] ?? false)) {
                    $content['admin_tombstone'] = true;
                }
                unset($content['admin_removed']);
                $values = ['reference' => $inventory->reference, 'content' => json_encode($content, JSON_THROW_ON_ERROR),
                    'price_cents' => $row->price_cents ?? max(0, (int) ($product['price_cents'] ?? $inventory->approved_price_cents ?? 0)),
                    'published' => $published, 'revision' => ($row->revision ?? 0) + 1, 'updated_at' => now()];
                if (! $row) {
                    $values['created_at'] = now();
                }
                $db->table('commerce_admin_products')->updateOrInsert(['slug' => $slug], $values);
                if (! $published) {
                    $db->table('commerce_inventory')->where('slug', $slug)->update(['sale_enabled' => false, 'updated_at' => now()]);
                }
                app(Activity::class)->record($published ? 'product_restored' : 'product_suspended', $slug, $db, $slug.'|visibility|'.$values['revision']);

                return $published;
            });
        });
    }

    public function deleteFromInventory(string $slug): void
    {
        $catalogue = app(Catalogue::class);
        $product = $catalogue->productForAdmin($slug);
        abort_unless($product, 404);
        $db = app(OrderStore::class)->connection();

        Cache::lock('admin-product-write', 30)->block(5, function () use ($db, $slug, $product) {
            $db->transaction(function () use ($db, $slug, $product) {
                $inventory = $db->table('commerce_inventory')->where('slug', $slug)->lockForUpdate()->first();
                abort_unless($inventory, 404);
                $row = $db->table('commerce_admin_products')->where('slug', $slug)->lockForUpdate()->first();
                $hasOrders = $db->table('commerce_order_items')->where('slug', $slug)->exists();
                if ((int) $inventory->reserved > 0 || $hasOrders) {
                    throw ValidationException::withMessages(['product' => 'Suppression impossible : ce produit a une réservation ou un historique de commande. Suspendez-le à la place.']);
                }
                $content = $row ? json_decode($row->content, true, 32, JSON_THROW_ON_ERROR) : $this->baseContent($product);
                $content['admin_removed'] = true;
                unset($content['admin_tombstone']);
                $values = ['reference' => $inventory->reference, 'content' => json_encode($content, JSON_THROW_ON_ERROR),
                    'price_cents' => $row->price_cents ?? 0,
                    'published' => false, 'revision' => ($row->revision ?? 0) + 1, 'updated_at' => now()];
                if (! $row) {
                    $values['created_at'] = now();
                }
                // Keep an unpublished tombstone so the file-backed catalogue cannot expose the removed product again.
                $db->table('commerce_admin_products')->updateOrInsert(['slug' => $slug], $values);
                $db->table('commerce_inventory')->where('slug', $slug)->delete();
                app(Activity::class)->record('product_deleted', $slug, $db, $slug.'|deleted|'.$values['revision']);
            });
        });
    }

    private function baseContent(array $product): array
    {
        $category = $product['category'] ?? [];
        $image = $product['image'] ?? null;

        return ['custom' => false, 'universe' => $product['universe'] ?? 'other', 'languages' => [],
            'condition' => $product['condition'] ?? null, 'brand' => $product['brand'] ?? '',
            'category' => ['slug' => $category['slug'] ?? 'autre', 'name' => $category['name'] ?? 'Autre'],
            'images' => $product['images'] ?? ($image ? [$image] : []), 'image' => $image];
    }

    private function image($upload): array
    {
        abort_unless(extension_loaded('gd'), 503);
        $dimensions = @getimagesize($upload->getPathname());
        if (! $dimensions || ! in_array($dimensions[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || min($dimensions[0], $dimensions[1]) < 500 || $dimensions[0] * $dimensions[1] > 16_000_000) {
            throw ValidationException::withMessages(['image' => 'Photo JPEG, PNG ou WebP, au moins 500 × 500 pixels, au plus 16 millions de pixels.']);
        }
        $decoded = @imagecreatefromstring(file_get_contents($upload->getPathname()));
        if (! $decoded) {
            throw ValidationException::withMessages(['image' => 'Cette image ne peut pas être décodée.']);
        }
        try {
            $name = Str::random(40).'.webp';
            $directory = public_path('media/admin-products');
            File::ensureDirectoryExists($directory, 0755);
            abort_if(is_link($directory), 503);
            $path = $directory.'/'.$name;
            if (! imagewebp($decoded, $path, 88)) {
                throw new \RuntimeException('IMAGE_WRITE_FAILED');
            }
            chmod($path, 0644);

            return ['local_url' => '/media/admin-products/'.$name, 'width' => $dimensions[0], 'height' => $dimensions[1]];
        } finally {
            unset($decoded);
        }
    }
}
