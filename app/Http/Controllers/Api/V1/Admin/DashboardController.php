<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    protected function ensureAdmin($user)
    {
        if (!in_array($user->role, ['admin', 'staff'])) {
            abort(403, 'Unauthorized');
        }
    }

    public function stats(Request $request)
    {
        $this->ensureAdmin($request->user());

        return response()->json([
            'pendingSuppliers' => Supplier::where('is_approved', false)->count(),
            'pendingProducts'  => Product::where('status', 'pending_review')->count(),
            'pendingPayments'  => Order::where('payment_status', 'pending_manual')->count(),
            'totalOrders'      => Order::count(),
        ]);
    }
}