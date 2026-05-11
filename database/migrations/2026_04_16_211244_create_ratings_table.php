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
            // If the table exists, just ensure the image column is present
            if (!Schema::hasColumn('ratings', 'image')) {
                Schema::table('ratings', function (Blueprint $table) {
                    $table->string('image')->nullable()->after('review');
                });
            }
        }

        // Also ensure the video_url column on products
        if (!Schema::hasColumn('products', 'video_url')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('video_url')->nullable()->after('images');
            });
        }

        // And the is_active column on users
        if (!Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('role');
            });
        }
    }

    public function down(): void
    {
        // Don't drop the table in production
    }
};