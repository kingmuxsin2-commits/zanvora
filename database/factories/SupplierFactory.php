<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_name' => fake()->company(),
            'address' => fake()->address(),
            'is_approved' => true,
            'shipping_flat_fee' => fake()->randomFloat(2, 0, 10),
            'payment_details' => json_encode([
                'paypal_email' => fake()->email(),
            ]),
        ];
    }

    /**
     * Set supplier as unapproved.
     */
    public function unapproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => false,
        ]);
    }
}