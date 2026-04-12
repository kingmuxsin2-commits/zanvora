<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductApprovalController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAdmin($request->user());

        $products = Product::with('supplier.user')
            ->where('status', 'pending_review')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($products);
    }

    public function approve(Request $request, Product $product)
    {
        $this->ensureAdmin($request->user());

        $product->update(['status' => 'active']);

        // Optionally notify supplier that product is live

        return response()->json(['message' => 'Product approved', 'product' => $product]);
    }

    public function reject(Request $request, Product $product)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $product->update([
            'status' => 'draft',
            // Could store rejection reason in a notes column or separate table
        ]);

        // Optionally notify supplier with reason

        return response()->json(['message' => 'Product rejected', 'product' => $product]);
    }

    public function all(Request $request)
    {
        $this->ensureAdmin($request->user());

        $products = Product::with('supplier.user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($products);
    }

    protected function ensureAdmin($user)
    {
        if (!in_array($user->role, ['admin', 'staff'])) {
            abort(403, 'Unauthorized');
        }
    }
}