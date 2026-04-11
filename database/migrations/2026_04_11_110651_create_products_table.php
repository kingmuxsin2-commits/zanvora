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
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
        $table->string('title');
        $table->text('description')->nullable();
        $table->decimal('wholesale_price', 10, 2);
        $table->decimal('retail_price', 10, 2);
        $table->integer('stock_qty')->default(0);
        $table->json('images')->nullable(); // array of URLs
        $table->json('variants')->nullable(); // e.g., sizes/colors
        $table->enum('status', ['draft', 'pending_review', 'active', 'paused'])
              ->default('draft');
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
