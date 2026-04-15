<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        
        if ($search) {
            // Use Meilisearch for full-text search
            $products = Product::search($search)
                ->query(function ($builder) {
                    $builder->with('supplier')
                        ->where('status', 'active')
                        ->where('stock_qty', '>', 0);
                })
                ->paginate(20);
        } else {
            // Fallback to standard Eloquent when no search term
            $query = Product::with('supplier')
                ->where('status', 'active')
                ->where('stock_qty', '>', 0);

            // Optional supplier filter
            if ($request->has('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            $products = $query->orderBy('created_at', 'desc')->paginate(20);
        }

        return response()->json($products);
    }

    public function show(Product $product)
    {
        if ($product->status !== 'active') {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $product->load('supplier');

        return response()->json($product);
    }
}