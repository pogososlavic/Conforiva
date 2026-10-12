<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'commerce';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);
        if (! $schema->hasColumn('commerce_orders', 'admin_viewed_at')) {
            $schema->table('commerce_orders', function (Blueprint $table) {
                $table->timestamp('admin_viewed_at')->nullable()->index();
            });
        }

        // Existing orders predate this read state; start tracking only new arrivals.
        DB::connection($this->connection)->table('commerce_orders')->whereNull('admin_viewed_at')
            ->update(['admin_viewed_at' => now()]);
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);
        if ($schema->hasColumn('commerce_orders', 'admin_viewed_at')) {
            $schema->table('commerce_orders', function (Blueprint $table) {
                $table->dropColumn('admin_viewed_at');
            });
        }
    }
};
