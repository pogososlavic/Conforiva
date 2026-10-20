<?php

namespace App\Services;

/** Reviewed display crops only. The original file and catalogue stay authoritative. */
final class CatalogueImageFraming
{
    private ?array $manifest = null;

    private array $frames = [];

    public function forImage(?string $url): ?array
    {
        if (! is_string($url) || ! preg_match('~\A/media/catalogue/[a-f0-9]{24}\.(?:jpg|png|webp|gif)\z~', $url)) {
            return null;
        }
        if (array_key_exists($url, $this->frames)) {
            return $this->frames[$url];
        }
        $this->frames[$url] = null;
        if ($this->manifest === null) {
            $path = config('catalogue.image_framing_path', resource_path('data/catalogue-image-framing.json'));
            $data = is_string($path) && is_file($path) && ! is_link($path) && filesize($path) <= 4_000_000
                ? json_decode(file_get_contents($path), true) : null;
            $this->manifest = ($data['version'] ?? null) === 1 && is_array($data['images'] ?? null) ? $data['images'] : [];
        }
        $entry = $this->manifest[$url] ?? null;
        $path = public_path(ltrim($url, '/'));
        if (! is_array($entry) || ! is_file($path) || is_link($path)
            || ! is_string($entry['sha256'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $entry['sha256'])
            || ! hash_equals($entry['sha256'], hash_file('sha256', $path))) {
            return null;
        }
        $width = $entry['width'] ?? null;
        $height = $entry['height'] ?? null;
        $bounds = $entry['bounds'] ?? null;
        if (! is_int($width) || ! is_int($height) || $width < 500 || $height < 500
            || $width * $height > 64_000_000 || ! is_array($bounds) || ! array_is_list($bounds) || count($bounds) !== 4
            || count(array_filter($bounds, 'is_int')) !== 4
            || $bounds[0] < 0 || $bounds[1] < 0 || $bounds[2] > $width || $bounds[3] > $height
            || $bounds[2] - $bounds[0] < 20 || $bounds[3] - $bounds[1] < 20) {
            return null;
        }
        $dimensions = @getimagesize($path);
        if (! $dimensions || $dimensions[0] !== $width || $dimensions[1] !== $height) {
            return null;
        }

        return $this->frames[$url] = ['src' => $url, 'width' => $width, 'height' => $height, 'bounds' => $bounds];
    }
}
