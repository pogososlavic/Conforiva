<?php

namespace Tests\Unit;

use App\Services\Commerce\OrderStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BankReceiptAmountValidationTest extends TestCase
{
    public static function nonPositiveAmounts(): array
    {
        return [[0], [-1], [PHP_INT_MIN]];
    }

    #[DataProvider('nonPositiveAmounts')]
    public function test_non_positive_receipts_are_rejected_before_resolving_a_database(int $amount): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Receipt invalid.');
        (new OrderStore)->confirmBankReceipt('fixture-order', $amount, 'fixture-reference');
    }
}
