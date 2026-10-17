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
        $schema->create('commerce_analytics_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('granted');
            $table->string('measurement_id', 32);
            $table->text('client_id_encrypted')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
        $schema->create('commerce_analytics_deliveries', function (Blueprint $table) {
            $table->uuid('order_id')->primary();
            $table->foreign('order_id')->references('id')->on('commerce_orders')->restrictOnDelete();
            $table->string('status', 24);
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Analytics history requires a reviewed retention plan before rollback.');
    }
};
