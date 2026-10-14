<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BankReceiptSecurityTest extends TestCase
{
    private string $fixture;

    private string $originalStorage;

    private string $receiptPath;

    private array $queries = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = realpath(sys_get_temp_dir()).'/homya-bank-receipt-security-'.Str::uuid();
        mkdir($this->fixture, 0700);
        $this->originalStorage = storage_path();
        $this->app->useStoragePath($this->fixture.'/storage');
        mkdir(storage_path('app/private/commerce/receipts'), 0700, true);
        $this->receiptPath = storage_path('app/private/commerce/receipts/fixture.json');
        $this->writeReceipt($this->receipt());
        Http::preventStrayRequests();
        Mail::fake();
        Log::spy();
        config(['database.connections.commerce' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('commerce');
        $db = DB::connection('commerce');
        $db->getSchemaBuilder()->create('commerce_orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->integer('total_cents');
        });
        $db->table('commerce_orders')->insert(['id' => $this->receipt()['order_id'], 'total_cents' => 24690]);
        $db->listen(function ($query) {
            $this->queries[] = $query->sql;
        });
    }

    protected function tearDown(): void
    {
        DB::purge('commerce');
        $this->app->useStoragePath($this->originalStorage);
        File::deleteDirectory($this->fixture);
        parent::tearDown();
    }

    private function receipt(): array
    {
        return ['order_id' => '11111111-1111-4111-8111-111111111111', 'amount_cents' => 24690,
            'currency' => 'EUR', 'merchant_verified' => true, 'bank_reference' => 'FIXTURE_PRIVATE_BANK_REFERENCE'];
    }

    private function writeReceipt(array $receipt): void
    {
        file_put_contents($this->receiptPath, json_encode($receipt, JSON_THROW_ON_ERROR));
        chmod($this->receiptPath, 0600);
        clearstatcache(true, $this->receiptPath);
    }

    public static function unsafePaths(): array
    {
        return [
            'private ancestor symlink' => ['app/private'],
            'commerce ancestor symlink' => ['app/private/commerce'],
            'receipts ancestor symlink' => ['app/private/commerce/receipts'],
            'receipt symlink' => ['leaf'],
            'receipt hardlink' => ['hardlink'],
            'external parent alias' => ['alias'],
            'traversal to receipt parent' => ['traversal'],
            'relative path' => ['relative'],
            'public file permissions' => ['file_mode'],
            'public parent permissions' => ['parent_mode'],
        ];
    }

    #[DataProvider('unsafePaths')]
    public function test_unsafe_receipt_paths_are_refused_before_sql(string $case): void
    {
        $path = $this->receiptPath;
        if (str_starts_with($case, 'app/')) {
            $source = storage_path($case);
            $target = $this->fixture.'/relocated';
            rename($source, $target);
            symlink($target, $source);
        } elseif ($case === 'leaf') {
            rename($path, $path.'.real');
            symlink($path.'.real', $path);
        } elseif ($case === 'hardlink') {
            link($path, $this->fixture.'/hardlinked-receipt.json');
        } elseif ($case === 'alias') {
            symlink(dirname($path), $this->fixture.'/parent-alias');
            $path = $this->fixture.'/parent-alias/fixture.json';
        } elseif ($case === 'traversal') {
            $path = dirname($path).'/../receipts/fixture.json';
        } elseif ($case === 'relative') {
            $path = 'fixture.json';
        } elseif ($case === 'file_mode') {
            chmod($path, 0644);
        } elseif ($case === 'parent_mode') {
            chmod(dirname($path), 0755);
        }
        clearstatcache();
        foreach ([false, true] as $apply) {
            $this->artisan('homya:confirm-bank-transfer', ['receipt' => $path, '--apply' => $apply])
                ->expectsOutputToContain('Bank receipt refused or unavailable. No private details displayed.')
                ->assertFailed();
        }
        $this->assertSame([], $this->queries);
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public static function invalidReceipts(): array
    {
        return [
            'extra field' => [['extra' => 'fixture']],
            'invalid order UUID' => [['order_id' => 'not-a-uuid']],
            'amount is a string' => [['amount_cents' => '24690']],
            'foreign currency' => [['currency' => 'USD']],
            'customer declaration' => [['merchant_verified' => false]],
            'empty reference' => [['bank_reference' => '  ']],
        ];
    }

    #[DataProvider('invalidReceipts')]
    public function test_invalid_receipt_fields_are_refused_before_sql(array $override): void
    {
        $this->writeReceipt(array_replace($this->receipt(), $override));
        $this->artisan('homya:confirm-bank-transfer', ['receipt' => $this->receiptPath])->assertFailed();
        $this->assertSame([], $this->queries);
    }

    public function test_oversized_or_deep_json_is_refused_before_sql(): void
    {
        $json = json_encode($this->receipt(), JSON_THROW_ON_ERROR);
        file_put_contents($this->receiptPath, str_pad($json, 8193));
        $this->artisan('homya:confirm-bank-transfer', ['receipt' => $this->receiptPath])->assertFailed();
        $this->assertSame([], $this->queries);
        file_put_contents($this->receiptPath, str_repeat('[', 9).'0'.str_repeat(']', 9));
        $this->artisan('homya:confirm-bank-transfer', ['receipt' => $this->receiptPath])->assertFailed();
        $this->assertSame([], $this->queries);
    }

    public function test_private_single_link_receipt_at_size_limit_can_be_previewed(): void
    {
        file_put_contents($this->receiptPath, str_pad(json_encode($this->receipt(), JSON_THROW_ON_ERROR), 8192));
        $this->artisan('homya:confirm-bank-transfer', ['receipt' => $this->receiptPath])
            ->expectsOutputToContain('"receipt_amount_matches":true,"applied":false')
            ->assertSuccessful();
        $this->assertCount(1, $this->queries);
        $this->assertStringStartsWith('select ', $this->queries[0]);
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }
}
