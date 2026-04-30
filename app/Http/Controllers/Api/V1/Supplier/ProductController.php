<?php

namespace App\Http\Controllers\Api\V1\Supplier;

use App\Models\Product;
use App\Models\CommissionTier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    // 1. List products with search & sort
    public function index(Request $request)
    {
        $supplier = $request->user()->supplier;
        if (!$supplier) {
            return response()->json(['message' => 'Supplier profile not found'], 404);
        }

        $query = Product::where('supplier_id', $supplier->id);

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');
        $allowed = ['title', 'wholesale_price', 'retail_price', 'stock_qty', 'created_at'];
        if (in_array($sort, $allowed)) {
            $query->orderBy($sort, $order);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return response()->json($query->paginate($request->get('per_page', 20)));
    }

    // 2. Store
    public function store(Request $request)
    {
        $supplier = $request->user()->supplier;
        if (!$supplier) {
            return response()->json(['message' => 'Supplier profile not found'], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'wholesale_price' => 'required|numeric|min:0',
            'stock_qty' => 'required|integer|min:0',
            'shipping_flat_fee' => 'nullable|numeric|min:0',
            'images' => 'required|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
            'variants' => 'nullable|array',
        ]);

        $wholesalePrice = $validated['wholesale_price'];
        $commissionTier = CommissionTier::where('min_price', '<=', $wholesalePrice)
            ->where('max_price', '>=', $wholesalePrice)
            ->where('is_active', true)
            ->first();

        $retailPrice = $wholesalePrice;
        if ($commissionTier) {
            $retailPrice = $wholesalePrice * (1 + $commissionTier->percentage / 100);
        }

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $imagePaths[] = Storage::url($path);
            }
        }

        $product = Product::create([
            'supplier_id' => $supplier->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'wholesale_price' => $wholesalePrice,
            'retail_price' => $retailPrice,
            'stock_qty' => $validated['stock_qty'],
            'images' => $imagePaths,
            'variants' => $validated['variants'] ?? null,
            'status' => 'pending_review',
        ]);

        return response()->json($product, 201);
    }

    // 3. Show
    public function show(Request $request, Product $product)
    {
        $supplier = $request->user()->supplier;
        if (!$supplier || $product->supplier_id !== $supplier->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return response()->json($product);
    }

    // 4. Update (with image handling)
    public function update(Request $request, Product $product)
    {
        $supplier = $request->user()->supplier;
        if (!$supplier || $product->supplier_id !== $supplier->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'wholesale_price' => 'sometimes|numeric|min:0',
            'stock_qty' => 'sometimes|integer|min:0',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
            'variants' => 'nullable|array',
            'status' => 'sometimes|in:draft,pending_review,active,paused',
            'existing_images' => 'nullable|string',
        ]);

        if (isset($validated['wholesale_price'])) {
            $commissionTier = CommissionTier::where('min_price', '<=', $validated['wholesale_price'])
                ->where('max_price', '>=', $validated['wholesale_price'])
                ->where('is_active', true)
                ->first();
            $validated['retail_price'] = $validated['wholesale_price'];
            if ($commissionTier) {
                $validated['retail_price'] = $validated['wholesale_price'] * (1 + $commissionTier->percentage / 100);
            }
        }

        if ($request->has('existing_images')) {
            $keptImages = json_decode($request->existing_images, true) ?? [];
            foreach ($product->images as $oldImage) {
                if (!in_array($oldImage, $keptImages)) {
                    $path = str_replace('/storage/', '', $oldImage);
                    Storage::disk('public')->delete($path);
                }
            }
            $product->images = $keptImages;
        }

        if ($request->hasFile('images')) {
            $newImages = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $newImages[] = Storage::url($path);
            }
            $product->images = array_merge($product->images ?? [], $newImages);
        }

        $product->fill($validated);
        $product->save();

        return response()->json($product);
    }

    // 5. Update stock only
    public function updateStock(Request $request, Product $product)
    {
        $supplier = $request->user()->supplier;
        if (!$supplier || $product->supplier_id !== $supplier->id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'stock_qty' => 'required|integer|min:0',
        ]);
        $product->update(['stock_qty' => $validated['stock_qty']]);
        return response()->json(['message' => 'Stock updated', 'stock_qty' => $product->stock_qty]);
    }

    // 6. Delete
    public function destroy(Request $request, Product $product)
    {
        $supplier = $request->user()->supplier;
        if (!$supplier || $product->supplier_id !== $supplier->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        foreach ($product->images ?? [] as $image) {
            $path = str_replace('/storage/', '', $image);
            Storage::disk('public')->delete($path);
        }
        $product->delete();
        return response()->json(['message' => 'Product deleted']);
    }

    // 7. Inventory overview (ERP‑style spreadsheet)
    public function inventory(Request $request)
    {
        $supplier = $request->user()->supplier;
        if (!$supplier) {
            return response()->json(['message' => 'Supplier profile not found'], 404);
        }

        $query = Product::where('supplier_id', $supplier->id)
            ->withCount(['orderItems as sales_count' => function ($q) {
                $q->whereHas('order', fn($o) => $o->where('payment_status', 'paid'));
            }])
            ->withSum(['orderItems as total_revenue' => function ($q) {
                $q->whereHas('order', fn($o) => $o->where('payment_status', 'paid'));
            }], DB::raw('COALESCE(wholesale_cost * quantity, 0)'))
            ->withMax(['orderItems as last_sale_date' => function ($q) {
                $q->whereHas('order', fn($o) => $o->where('payment_status', 'paid'));
            }], 'created_at');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');
        $allowed = ['title', 'wholesale_price', 'retail_price', 'stock_qty',
                     'sales_count', 'total_revenue', 'last_sale_date', 'created_at'];
        if (in_array($sort, $allowed)) {
            $query->orderBy($sort, $order);
        }

        return response()->json($query->paginate(15));
    }
}