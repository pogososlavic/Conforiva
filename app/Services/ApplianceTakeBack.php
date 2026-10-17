<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class ApplianceTakeBack
{
    public function eligible(array $product): bool
    {
        return in_array($product['universe'] ?? null, ['appliances', 'televisions'], true);
    }

    public function snapshot(array $quote, array $requested): array
    {
        $lines = collect($quote['lines'])->keyBy('key');
        $result = [];
        foreach ($requested as $key => $quantity) {
            if ((int) $quantity === 0) {
                continue;
            }
            $line = $lines->get($key);
            if (! $line || ! $this->eligible($line['product']) || (int) $quantity > $line['quantity'] || (int) $quantity < 0) {
                throw ValidationException::withMessages(['take_back' => __('takeback.invalid')]);
            }
            $result[] = ['line_key' => $key, 'name' => $line['product']['name'], 'reference' => $line['product']['reference'],
                'quantity' => (int) $quantity, 'fee_cents' => 0, 'method' => 'at_delivery'];
        }

        return $result;
    }
}
