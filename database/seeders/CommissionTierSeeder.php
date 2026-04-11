<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommissionTierSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('commission_tiers')->insert([
            [
                'min_price' => 0.00,
                'max_price' => 20.00,
                'percentage' => 20.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'min_price' => 20.01,
                'max_price' => 50.00,
                'percentage' => 15.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'min_price' => 50.01,
                'max_price' => 999999.99,
                'percentage' => 10.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}