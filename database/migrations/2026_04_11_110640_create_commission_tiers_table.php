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
    Schema::create('commission_tiers', function (Blueprint $table) {
        $table->id();
        $table->decimal('min_price', 10, 2);
        $table->decimal('max_price', 10, 2);
        $table->decimal('percentage', 5, 2); // e.g., 15.00
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_tiers');
    }
};
