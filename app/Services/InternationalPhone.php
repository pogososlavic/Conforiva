<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class InternationalPhone
{
    public function countries(string $locale): array
    {
        $names = json_decode(file_get_contents(resource_path('data/phone-country-names.json')), true, flags: JSON_THROW_ON_ERROR);
        $util = PhoneNumberUtil::getInstance();
        $countries = [];
        foreach ($names[$locale] ?? $names['fr'] as $region => $name) {
            if (in_array($region, $util->getSupportedRegions(), true)) {
                $countries[$region] = ['name' => $name, 'prefix' => '+'.$util->getCountryCodeForRegion($region)];
            }
        }

        return $countries;
    }

    public function normalize(?string $phone, ?string $region): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }
        // Older forms/integrations without the selector keep their existing validated value.
        if ($region === null) {
            return $phone;
        }
        $util = PhoneNumberUtil::getInstance();
        if (! in_array($region, $util->getSupportedRegions(), true)) {
            $this->invalid();
        }
        try {
            $parsed = $util->parse($phone, $region);
            if (! $util->isPossibleNumber($parsed) || $parsed->getCountryCode() !== $util->getCountryCodeForRegion($region)) {
                $this->invalid();
            }

            return $util->format($parsed, PhoneNumberFormat::E164);
        } catch (NumberParseException) {
            $this->invalid();
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['phone' => __('commerce.invalid_phone')]);
    }
}
