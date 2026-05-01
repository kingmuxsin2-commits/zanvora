<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\User;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class AdminControlsController extends Controller
{
    protected function ensureAdmin($user)
    {
        if (!in_array($user->role, ['admin', 'staff'])) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * List all customers with last order date as last activity.
     */
    public function customers(Request $request)
    {
        $this->ensureAdmin($request->user());

        $customers = User::where('role', 'customer')
            ->withMax('orders as last_activity', 'created_at')
            ->orderBy('last_activity', 'desc')
            ->get(['id', 'name', 'email', 'phone', 'is_active', 'created_at']);

        return response()->json($customers);
    }

    /**
     * List all suppliers with last product/order activity.
     */
    public function suppliers(Request $request)
    {
        $this->ensureAdmin($request->user());

        $suppliers = Supplier::with('user')
            ->get()
            ->map(function ($supplier) {
                $lastOrder = \App\Models\OrderItem::where('supplier_id', $supplier->id)
                    ->max('created_at');
                $lastProduct = $supplier->products()->max('created_at');
                $lastActivity = max($lastOrder, $lastProduct);

                return [
                    'id'             => $supplier->id,
                    'user_id'        => $supplier->user->id,
                    'name'           => $supplier->user->name,
                    'email'          => $supplier->user->email,
                    'phone'          => $supplier->user->phone,
                    'business_name'  => $supplier->business_name,
                    'is_approved'    => $supplier->is_approved,
                    'is_active'      => $supplier->user->is_active,
                    'last_activity'  => $lastActivity,
                    'created_at'     => $supplier->created_at,
                ];
            })
            ->sortByDesc('last_activity')
            ->values();

        return response()->json($suppliers);
    }

    /**
     * Toggle customer active/inactive.
     */
    public function toggleUserActive(Request $request, User $user)
    {
        $this->ensureAdmin($request->user());

        if ($user->role !== 'customer') {
            return response()->json(['message' => 'Only customers can be toggled'], 400);
        }

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'message'   => $user->is_active ? 'Customer activated' : 'Customer deactivated',
            'is_active' => $user->is_active,
        ]);
    }

    /**
     * Toggle supplier approved/deactivated.
     */
    public function toggleSupplierStatus(Request $request, Supplier $supplier)
    {
        $this->ensureAdmin($request->user());

        $supplier->update(['is_approved' => !$supplier->is_approved]);

        return response()->json([
            'message'     => $supplier->is_approved ? 'Supplier activated' : 'Supplier deactivated',
            'is_approved' => $supplier->is_approved,
        ]);
    }

    /**
     * Reset password for a user and return the new plain‑text password.
     */
    public function resetPassword(Request $request, User $user)
    {
        $this->ensureAdmin($request->user());

        $newPassword = Str::random(10);
        $user->update(['password' => Hash::make($newPassword)]);

        return response()->json([
            'message'      => 'Password reset successfully.',
            'new_password' => $newPassword,
        ]);
    }
}