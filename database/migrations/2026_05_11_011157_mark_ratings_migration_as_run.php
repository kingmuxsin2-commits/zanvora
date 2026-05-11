<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Manually create the ratings table (idempotent)
        if (!Schema::hasTable('ratings')) {
            Schema::create('ratings', function ($table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->tinyInteger('rating')->unsigned()->comment('1-5 stars');
                $table->text('review')->nullable();
                $table->string('image')->nullable();
                $table->timestamps();
                $table->unique(['product_id', 'customer_id']);
            });
        }

        // Also ensure the video_url column exists on products
        if (!Schema::hasColumn('products', 'video_url')) {
            Schema::table('products', function ($table) {
                $table->string('video_url')->nullable()->after('images');
            });
        }

        // And the is_active column on users
        if (!Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function ($table) {
                $table->boolean('is_active')->default(true)->after('role');
            });
        }
    }

    public function down(): void
    {
        // No rollback
    }
};