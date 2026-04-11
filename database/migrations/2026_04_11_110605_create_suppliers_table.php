<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
 public function up(): void
{
    Schema::create('suppliers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('business_name');
        $table->text('address')->nullable();
        $table->boolean('is_approved')->default(false);
        $table->decimal('shipping_flat_fee', 10, 2)->default(0.00);
        $table->json('payment_details')->nullable(); // PayPal/Venmo info
        $table->timestamps();
    });
}
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
