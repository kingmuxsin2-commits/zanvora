<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $wholesale = fake()->randomFloat(2, 5, 50);
        $markup = fake()->randomFloat(2, 1.1, 2.0); // 10% to 100% markup
        
        return [
            'supplier_id' => Supplier::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'wholesale_price' => $wholesale,
            'retail_price' => round($wholesale * $markup, 2),
            'stock_qty' => fake()->numberBetween(1, 100),
            'images' => json_encode([fake()->imageUrl()]),
            'variants' => null,
            'status' => 'active',
        ];
    }

    /**
     * Set product status to pending_review.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_review',
        ]);
    }

    /**
     * Set product status to draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Set product with low stock.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_qty' => fake()->numberBetween(1, 3),
        ]);
    }

    /**
     * Set product as out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_qty' => 0,
        ]);
    }
}