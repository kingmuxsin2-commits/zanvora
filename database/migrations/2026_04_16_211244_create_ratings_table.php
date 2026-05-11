<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
            $hasColumn = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'ratings' AND column_name = 'image'");
            if (empty($hasColumn)) {
                Schema::table('ratings', function (Blueprint $table) {
                    $table->string('image')->nullable()->after('review');
                });
            }
        }

        // Safely add video_url to products if missing
        $videoColumnExists = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'products' AND column_name = 'video_url'");
        if (empty($videoColumnExists)) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('video_url')->nullable()->after('images');
            });
        }

        // Safely add is_active to users if missing
        $activeColumnExists = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'is_active'");
        if (empty($activeColumnExists)) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('role');
            });
        }
    }

    public function down(): void
    {
        // No rollback in production
    }
};