<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['payment_status', 'status']);
            $table->index('created_at');
            $table->index('payment_confirmed_at');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('supplier_id');
        });

        Schema::table('fulfillments', function (Blueprint $table) {
            $table->index(['supplier_id', 'status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('supplier_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status', 'status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['payment_confirmed_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['supplier_id']);
        });

        Schema::table('fulfillments', function (Blueprint $table) {
            $table->dropIndex(['supplier_id', 'status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['supplier_id']);
            $table->dropIndex(['status']);
        });
    }
};