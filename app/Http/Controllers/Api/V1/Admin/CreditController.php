<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Order;
use App\Models\Credit;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CreditController extends Controller
{
    protected function ensureAdmin($user)
    {
        if (!in_array($user->role, ['admin', 'staff'])) {
            abort(403, 'Unauthorized');
        }
    }

    public function store(Request $request, Order $order)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
        ]);

        $credit = Credit::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'amount' => $validated['amount'],
            'reason' => $validated['reason'],
            'issued_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Credit issued successfully.',
            'credit' => $credit,
        ], 201);
    }

    public function index(Request $request, Order $order)
    {
        $this->ensureAdmin($request->user());

        $credits = Credit::where('order_id', $order->id)
            ->with('issuer')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($credits);
    }
}