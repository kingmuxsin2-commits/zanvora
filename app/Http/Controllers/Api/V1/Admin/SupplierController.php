<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SupplierController extends Controller
{
    protected function ensureAdmin()
    {
        if (!auth()->user() || !in_array(auth()->user()->role, ['admin', 'staff'])) {
            abort(403, 'Unauthorized');
        }
    }

    public function index()
    {
        $this->ensureAdmin();
        return Supplier::with('user')->latest()->paginate(20);
    }

    public function pending()
    {
        $this->ensureAdmin();
        return Supplier::with('user')->where('is_approved', false)->latest()->paginate(20);
    }

    public function approve(Supplier $supplier)
    {
        $this->ensureAdmin();
        
        $supplier->update(['is_approved' => true]);
        
        return response()->json([
            'message' => 'Supplier approved successfully',
            'supplier' => $supplier->load('user')
        ]);
    }

    public function reject(Supplier $supplier)
    {
        $this->ensureAdmin();
        
        // Optionally delete or keep as unapproved
        
        return response()->json(['message' => 'Supplier rejected']);
    }

    /**
     * Get all suppliers (for filter dropdowns).
     */
    public function allSuppliers(Request $request)
    {
        $this->ensureAdmin();

        $suppliers = Supplier::select('id', 'business_name')
            ->orderBy('business_name')
            ->get();

        return response()->json($suppliers);
    }
}