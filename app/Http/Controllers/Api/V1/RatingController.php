<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\Rating;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RatingController extends Controller
{
    /**
     * Store or update a rating for a product.
     */
    public function store(Request $request, Product $product)
    {
        $user = $request->user();

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
        ]);

        $rating = Rating::updateOrCreate(
            [
                'product_id'  => $product->id,
                'customer_id' => $user->id,
            ],
            [
                'rating' => $validated['rating'],
                'review' => $validated['review'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Rating submitted successfully',
            'rating'  => $rating,
        ]);
    }
}