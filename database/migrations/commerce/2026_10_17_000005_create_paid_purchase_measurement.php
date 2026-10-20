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
            // Older MariaDB otherwise assigns a zero default to a second TIMESTAMP,
            // which is rejected by Laravel's strict NO_ZERO_DATE connection.
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Purchase measurement retention must be reviewed before rollback.');
    }
};
