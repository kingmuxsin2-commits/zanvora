<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    /**
     * Display a listing of products with filtering, sorting, and search.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $perPage = min(max((int) $perPage, 10), 100); // clamp between 10 and 100

        $search = $request->get('search');

        // Base query for active products with stock
        $query = Product::with('supplier')
            ->where('status', 'active')
            ->where('stock_qty', '>', 0);

        // Full‑text search via Meilisearch
        if ($search) {
            $productIds = Product::search($search)
                ->query(fn($builder) => $builder->select('id'))
                ->take(500)
                ->get()
                ->pluck('id');

            $query->whereIn('id', $productIds);
        }

        // Supplier filter
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', (int) $request->supplier_id);
        }

        // Sorting
        $sort = $request->get('sort', 'newest');
        switch ($sort) {
            case 'price_asc':
                $query->orderBy('retail_price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('retail_price', 'desc');
                break;
            case 'best_selling':
                // Subquery on order_items for paid orders to get sales count
                $query->withCount(['orderItems as sales_count' => function ($q) {
                    $q->whereHas('order', fn($o) => $o->where('payment_status', 'paid'));
                }])->orderBy('sales_count', 'desc');
                break;
            case 'random':
                $query->inRandomOrder();
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        if ($product->status !== 'active') {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $product->load([
            'supplier',
            'ratings' => function ($query) {
                $query->with('customer:id,name')->latest()->limit(20);
            },
        ]);

        // ✅ Attach a `verified_purchase` flag to each rating
        $product->ratings->each(function ($rating) {
            $rating->verified_purchase = OrderItem::where('product_id', $rating->product_id)
                ->whereHas('order', fn($q) => $q->where('customer_id', $rating->customer_id)->where('payment_status', 'paid'))
                ->exists();
        });

        return response()->json($product);
    }
}