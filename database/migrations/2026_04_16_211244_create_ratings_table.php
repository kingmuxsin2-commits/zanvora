<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create the ratings table if it does not exist
        if (!Schema::hasTable('ratings')) {
            Schema::create('ratings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->tinyInteger('rating')->unsigned()->comment('1-5 stars');
                $table->text('review')->nullable();
                $table->string('image')->nullable();
                $table->timestamps();
                $table->unique(['product_id', 'customer_id']);
            });
        } else {
            // If table exists, ensure image column is present
            if (!Schema::hasColumn('ratings', 'image')) {
                Schema::table('ratings', function (Blueprint $table) {
                    $table->string('image')->nullable()->after('review');
                });
            }
        }
    }

    public function down(): void
    {
        // No rollback in production
    }
};