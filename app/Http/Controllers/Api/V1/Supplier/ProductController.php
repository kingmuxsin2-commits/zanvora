<?php

namespace App\Http\Controllers\Api\V1\Supplier;

use App\Models\Product;
use App\Models\CommissionTier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProductController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $supplier = $request->user()->supplier;
        
        if (!$supplier) {
            return response()->json(['message' => 'Supplier profile not found'], 404);
        }

        $products = Product::where('supplier_id', $supplier->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($products);
    }

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

        // Calculate retail price based on commission tiers
        $wholesalePrice = $validated['wholesale_price'];
        $commissionTier = CommissionTier::where('min_price', '<=', $wholesalePrice)
            ->where('max_price', '>=', $wholesalePrice)
            ->where('is_active', true)
            ->first();

        $retailPrice = $wholesalePrice;
        if ($commissionTier) {
            $retailPrice = $wholesalePrice * (1 + $commissionTier->percentage / 100);
        }

        // Handle image uploads
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
            'status' => 'pending_review', // All new products require admin approval
        ]);

        return response()->json($product, 201);
    }

    public function show(Request $request, Product $product)
    {
        $supplier = $request->user()->supplier;
        
        if (!$supplier || $product->supplier_id !== $supplier->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($product);
    }

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

        // Recalculate retail price if wholesale price changed
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

        // Process kept existing images
        if ($request->has('existing_images')) {
            $keptImages = json_decode($request->existing_images, true) ?? [];
            // Delete images that are no longer kept
            foreach ($product->images as $oldImage) {
                if (!in_array($oldImage, $keptImages)) {
                    $path = str_replace('/storage/', '', $oldImage);
                    Storage::disk('public')->delete($path);
                }
            }
            $product->images = $keptImages;
        }

        // Handle new image uploads
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

    public function destroy(Request $request, Product $product)
    {
        $supplier = $request->user()->supplier;
        
        if (!$supplier || $product->supplier_id !== $supplier->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete associated images
        foreach ($product->images ?? [] as $image) {
            $path = str_replace('/storage/', '', $image);
            Storage::disk('public')->delete($path);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted']);
    }
}