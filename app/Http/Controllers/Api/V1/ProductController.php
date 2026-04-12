<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('supplier')
            ->where('status', 'active')
            ->where('stock_qty', '>', 0);

        // Optional search
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Optional supplier filter
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $products = $query->orderBy('created_at', 'desc')
            ->paginate(20);

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