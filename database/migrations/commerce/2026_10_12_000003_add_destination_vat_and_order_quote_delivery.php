<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'commerce';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);
        $schema->table('commerce_order_items', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('commerce_order_items', 'vat_class')) {
                $table->string('vat_class', 32)->nullable();
            }
            if (! $schema->hasColumn('commerce_order_items', 'vat_rate_basis')) {
                // Hundredths of one percent: 2000 represents 20.00%.
                $table->unsignedInteger('vat_rate_basis')->nullable();
            }
        });

        if (! $schema->hasTable('commerce_order_quotes')) {
            $schema->create('commerce_order_quotes', function (Blueprint $table) {
                $table->uuid('order_id')->primary();
                $table->foreign('order_id')->references('id')->on('commerce_orders')->restrictOnDelete();
                $table->char('document_id', 36)->nullable()->unique();
                $table->string('status', 24)->default('preparing');
                $table->string('blocked_reason', 48)->nullable();
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('attempt_started_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'updated_at']);
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Order tax snapshots and quote delivery history require a reviewed data-retention plan before rollback.');
    }
};
