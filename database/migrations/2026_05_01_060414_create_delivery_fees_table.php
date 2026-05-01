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
        Schema::create('delivery_fees', function (Blueprint $table) {
            $table->id();
            $table->string('xafad');              // e.g. "Jameeco"
            $table->string('degmo');              // e.g. "Maxamuud Haybe"
            $table->decimal('price', 10, 2);      // delivery fee in USD
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['xafad', 'degmo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_fees');
    }
};