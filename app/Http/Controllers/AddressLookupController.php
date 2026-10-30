<?php

namespace App\Http\Controllers;

use App\Services\Commerce\CheckoutReadiness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

final class AddressLookupController extends CommerceController
{
    private const STREET_HIGHWAYS = [
        'motorway', 'motorway_link', 'trunk', 'trunk_link', 'primary', 'primary_link',
        'secondary', 'secondary_link', 'tertiary', 'tertiary_link', 'unclassified',
        'residential', 'living_street', 'service', 'pedestrian', 'road', 'track',
        'footway', 'path', 'cycleway', 'steps',
    ];

    public function __invoke(Request $request, CheckoutReadiness $readiness)
    {
        abort_unless($readiness->ready(), 503);
        $data = $request->validate(['address' => ['required', 'string', 'min:3', 'max:200'], 'country' => ['required', 'in:FR,BE,DE']]);
        try {
            $country = $data['country'];
            $client = Http::acceptJson()->connectTimeout(2)->timeout(4)->withoutRedirecting();
            $response = $country === 'FR'
                ? $client->get('https://data.geopf.fr/geocodage/search', ['q' => $data['address'], 'index' => 'address', 'limit' => 6])
                : $client->get('https://photon.komoot.io/api/', ['q' => $data['address'], 'countrycode' => $country,
                    'lang' => $request->route('locale'), 'limit' => 6]);
            abort_unless($response->successful() && strlen($response->body()) < 100000, 503);
            $features = $response->json('features');
            abort_unless(is_array($features), 503);
            $suggestions = [];
            foreach (array_slice($features, 0, 6) as $feature) {
                $entry = is_array($feature) ? ($feature['properties'] ?? null) : null;
                if (! is_array($entry)) {
                    continue;
                }
                if ($country !== 'FR') {
                    $entryCountry = is_string($entry['countrycode'] ?? null) ? strtoupper(trim($entry['countrycode'])) : '';
                    if ($entryCountry !== $country) {
                        continue;
                    }
                    $street = is_string($entry['street'] ?? null) ? trim($entry['street']) : '';
                    // Photon street features can expose the road name as `name`, without `street`.
                    // Do not treat the name of an arbitrary point of interest as a street.
                    $isStreet = ($entry['type'] ?? null) === 'street'
                        || (! isset($entry['type']) && ($entry['osm_key'] ?? null) === 'highway'
                            && in_array($entry['osm_value'] ?? null, self::STREET_HIGHWAYS, true));
                    if ($street === '' && $isStreet
                        && is_string($entry['name'] ?? null)) {
                        $street = trim($entry['name']);
                    }
                    if ($street === '') {
                        continue;
                    }
                    $number = is_string($entry['housenumber'] ?? null) ? trim($entry['housenumber']) : '';
                    // Street-level results retain the house number already typed by the customer.
                    if ($number === '') {
                        $typedNumber = '';
                        if (preg_match('/\A([0-9]{1,4}[a-zA-Z]?(?:\s+(?:bis|ter))?)\s+/u', trim($data['address']), $match)
                            || preg_match('/\s+([0-9]{1,4}[a-zA-Z]?(?:[-\/][0-9]{1,4}[a-zA-Z]?)?(?:\s+(?:bis|ter))?)\z/ui', trim($data['address']), $match)) {
                            $typedNumber = $match[1];
                        }
                        if ($typedNumber !== ($entry['postcode'] ?? null)) {
                            $number = $typedNumber;
                        }
                    }
                    $entry['name'] = trim($country === 'DE' ? $street.' '.$number : $number.' '.$street);
                }
                if (! is_string($entry['name'] ?? null) || trim($entry['name']) === '' || ! is_string($entry['city'] ?? null)
                    || trim($entry['city']) === '' || ! is_string($entry['postcode'] ?? null)
                    || ! preg_match($country === 'BE' ? '/\A[0-9]{4}\z/' : '/\A[0-9]{5}\z/', $entry['postcode'])
                    || mb_strlen($entry['name']) > 200 || mb_strlen($entry['city']) > 100) {
                    continue;
                }
                $suggestion = ['address' => $entry['name'], 'postal_code' => $entry['postcode'], 'city' => $entry['city']];
                if (! in_array($suggestion, $suggestions, true)) {
                    $suggestions[] = $suggestion;
                }
            }

            return response()->json(['suggestions' => $suggestions]);
        } catch (Throwable) {
            return response()->json(['suggestions' => [], 'unavailable' => true], 503);
        }
    }
}
