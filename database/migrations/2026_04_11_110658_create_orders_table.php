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
    Schema::create('orders', function (Blueprint $table) {
        $table->id();
        $table->string('order_number')->unique(); // ORD-20250411-001
        $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
        $table->decimal('total_amount', 10, 2);
        $table->json('shipping_address');
        $table->enum('status', [
            'pending_payment', 'paid', 'processing',
            'partial_shipped', 'shipped', 'delivered', 'cancelled'
        ])->default('pending_payment');
        $table->string('payment_method')->default('mobile_money');
        $table->string('payment_reference'); // order_number used as reference
        $table->string('payment_phone')->nullable();
        $table->enum('payment_status', ['pending_manual', 'paid', 'cancelled'])
              ->default('pending_manual');
        $table->foreignId('payment_confirmed_by')->nullable()->constrained('users');
        $table->timestamp('payment_confirmed_at')->nullable();
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
