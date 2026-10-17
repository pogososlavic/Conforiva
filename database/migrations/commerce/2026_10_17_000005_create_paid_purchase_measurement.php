<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'commerce';

    public function up(): void
    {
        Schema::connection($this->connection)->create('commerce_paid_purchases', function (Blueprint $table) {
            $table->uuid('order_id')->primary();
            $table->foreign('order_id')->references('id')->on('commerce_orders')->restrictOnDelete();
            $table->timestamp('paid_at')->index();
            $table->unsignedInteger('received_cents');
            $table->string('currency', 3);
            $table->mediumText('event_encrypted');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Purchase measurement retention must be reviewed before rollback.');
    }
};
