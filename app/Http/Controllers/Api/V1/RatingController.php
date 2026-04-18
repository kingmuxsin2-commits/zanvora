<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\Rating;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class RatingController extends Controller
{
    /**
     * Store or update a rating for a product.
     * Only customers who have purchased the product can rate it.
     */
    public function store(Request $request, Product $product)
    {
        $user = $request->user();

        // ✅ Verify that the customer has purchased this product
        $hasPurchased = OrderItem::where('product_id', $product->id)
            ->whereHas('order', function ($query) use ($user) {
                $query->where('customer_id', $user->id)
                      ->where('payment_status', 'paid');
            })
            ->exists();

        if (!$hasPurchased) {
            return response()->json([
                'message' => 'You can only review products you have purchased.'
            ], 403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
            'image'  => 'nullable|image|max:5120', // max 5MB
        ]);

        $data = [
            'rating' => $validated['rating'],
            'review' => $validated['review'] ?? null,
        ];

        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('reviews', 'public');
            $data['image'] = Storage::url($path);
        }

        $rating = Rating::updateOrCreate(
            [
                'product_id'  => $product->id,
                'customer_id' => $user->id,
            ],
            $data
        );

        return response()->json([
            'message' => 'Rating submitted successfully',
            'rating'  => $rating,
        ]);
    }
}