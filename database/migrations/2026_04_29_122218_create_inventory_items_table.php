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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('product_name');
            $table->decimal('wholesale_price', 10, 2)->nullable();
            $table->date('wholesale_price_date')->nullable();
            $table->decimal('retail_price', 10, 2)->nullable();
            $table->date('retail_price_date')->nullable();
            $table->integer('initial_stock')->default(0);
            $table->integer('current_stock')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};