<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductApprovalController extends Controller
{
    protected function ensureAdmin($user)
    {
        if (!in_array($user->role, ['admin', 'staff'])) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Get pending products (for approval queue).
     */
    public function index(Request $request)
    {
        $this->ensureAdmin($request->user());

        $products = Product::with('supplier')
            ->where('status', 'pending_review')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($products);
    }

    /**
     * Approve a product.
     */
    public function approve(Request $request, Product $product)
    {
        $this->ensureAdmin($request->user());

        $product->update(['status' => 'active']);

        return response()->json(['message' => 'Product approved', 'product' => $product]);
    }

    /**
     * Reject a product (set status back to draft).
     */
    public function reject(Request $request, Product $product)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $product->update(['status' => 'draft']);

        // TODO: store rejection reason if needed

        return response()->json(['message' => 'Product rejected', 'product' => $product]);
    }

    /**
     * Get all products with advanced filters.
     */
    public function all(Request $request)
    {
        $this->ensureAdmin($request->user());

        $query = Product::with('supplier');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by supplier
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('supplier', fn($s) => $s->where('business_name', 'like', "%{$search}%"));
            });
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($products);
    }

    /**
     * Bulk approve products.
     */
    public function bulkApprove(Request $request)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:products,id',
        ]);

        Product::whereIn('id', $validated['ids'])->update(['status' => 'active']);

        return response()->json(['message' => count($validated['ids']) . ' products approved']);
    }

    /**
     * Bulk reject products (set to draft).
     */
    public function bulkReject(Request $request)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:products,id',
        ]);

        Product::whereIn('id', $validated['ids'])->update(['status' => 'draft']);

        return response()->json(['message' => count($validated['ids']) . ' products rejected']);
    }

    /**
     * Get all suppliers (for filter dropdown).
     */
    public function allSuppliers(Request $request)
    {
        $this->ensureAdmin($request->user());

        $suppliers = Supplier::select('id', 'business_name')
            ->orderBy('business_name')
            ->get();

        return response()->json($suppliers);
    }
}