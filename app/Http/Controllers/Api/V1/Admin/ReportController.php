<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    protected function ensureAdmin($user)
    {
        if (!in_array($user->role, ['admin', 'staff'])) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Generate weekly payout report for suppliers.
     */
    public function payoutReport(Request $request)
    {
        $this->ensureAdmin($request->user());

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        // Default to last 30 days (UTC)
        $startDate = $request->start_date
            ? now()->parse($request->start_date)->startOfDay()->utc()
            : now()->subDays(30)->startOfDay()->utc();

        $endDate = $request->end_date
            ? now()->parse($request->end_date)->endOfDay()->utc()
            : now()->endOfDay()->utc();

        // Get suppliers with total owed from paid orders in date range
        $suppliers = Supplier::with('user')
            ->where('is_approved', true)
            ->get()
            ->map(function ($supplier) use ($startDate, $endDate) {
                // Sum wholesale_cost from order_items for PAID orders within date range
                $totalOwed = DB::table('order_items')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->where('order_items.supplier_id', $supplier->id)
                    ->where('orders.payment_status', 'paid')
                    ->whereBetween('orders.payment_confirmed_at', [$startDate, $endDate])
                    ->sum('order_items.wholesale_cost');

                return [
                    'id'              => $supplier->id,
                    'business_name'   => $supplier->business_name,
                    'email'           => $supplier->user->email,
                    'payment_details' => $supplier->payment_details,
                    'total_owed'      => round((float) $totalOwed, 2),
                ];
            })
            ->filter(fn($s) => $s['total_owed'] > 0)
            ->values();

        return response()->json([
            'start_date'   => $startDate->toDateString(),
            'end_date'     => $endDate->toDateString(),
            'suppliers'    => $suppliers,
            'total_payout' => round($suppliers->sum('total_owed'), 2),
        ]);
    }

    /**
     * Mark a supplier as paid (manual tracking).
     */
    public function markPaid(Request $request, Supplier $supplier)
    {
        $this->ensureAdmin($request->user());

        $request->validate([
            'amount' => 'required|numeric|min:0',
            'notes'  => 'nullable|string',
        ]);

        // In a full implementation, you'd log this to a payout_logs table.
        // For now, we simply return a success response.

        return response()->json([
            'message'   => 'Supplier marked as paid',
            'supplier'  => $supplier->business_name,
            'amount'    => $request->amount,
        ]);
    }
}