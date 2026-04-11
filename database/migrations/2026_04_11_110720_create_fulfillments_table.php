<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('fulfillments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id')->constrained()->cascadeOnDelete();
        $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
        $table->enum('status', ['awaiting_payment', 'pending', 'shipped', 'delivered'])
              ->default('awaiting_payment');
        $table->string('tracking_number')->nullable();
        $table->string('carrier')->nullable();
        $table->decimal('shipping_cost', 10, 2)->default(0.00);
        $table->timestamp('shipped_at')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfillments');
    }
};
