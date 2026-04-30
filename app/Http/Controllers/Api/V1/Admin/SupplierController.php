<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\User;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;

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

    /**
     * Create a new supplier (admin only).
     */
    public function store(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'phone'          => 'required|string|max:20',
            'business_name'  => 'required|string|max:255',
            'address'        => 'required|string',
            'password'       => 'required|string|min:6',
        ]);

        // Create the user with supplier role
        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'phone'    => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role'     => 'supplier',
        ]);

        // Create supplier profile (auto‑approved because admin created it)
        $supplier = Supplier::create([
            'user_id'        => $user->id,
            'business_name'  => $validated['business_name'],
            'address'        => $validated['address'],
            'is_approved'    => true,
        ]);

        return response()->json([
            'message'  => 'Supplier created successfully.',
            'supplier' => $supplier->load('user'),
        ], 201);
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

    /**
     * Toggle supplier active/inactive status (activate/deactivate).
     */
    public function toggleStatus(Request $request, Supplier $supplier)
    {
        $this->ensureAdmin();

        $supplier->update(['is_approved' => !$supplier->is_approved]);

        return response()->json([
            'message'  => $supplier->is_approved ? 'Supplier activated.' : 'Supplier deactivated.',
            'supplier' => $supplier->fresh(),
        ]);
    }
}