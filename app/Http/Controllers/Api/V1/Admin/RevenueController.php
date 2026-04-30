<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{
    protected function ensureAdmin($user)
    {
        if (!in_array($user->role, ['admin', 'staff'])) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Get platform revenue breakdown (commission + subscriptions).
     */
    public function index(Request $request)
    {
        $this->ensureAdmin($request->user());

        $startDate = $request->start_date
            ? now()->parse($request->start_date)->startOfDay()
            : now()->subDays(30)->startOfDay();

        $endDate = $request->end_date
            ? now()->parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        // Commission from paid orders
        $commissionTotal = OrderItem::whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->where('payment_status', 'paid')
                      ->whereBetween('payment_confirmed_at', [$startDate, $endDate]);
            })
            ->sum('commission_earned');

        // TODO: Replace with actual subscription revenue when implemented
        $subscriptionTotal = 0;

        $totalRevenue = $commissionTotal + $subscriptionTotal;

        return response()->json([
            'period' => [
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ],
            'commission'    => round((float) $commissionTotal, 2),
            'subscriptions' => round((float) $subscriptionTotal, 2),
            'total'         => round((float) $totalRevenue, 2),
        ]);
    }

    /**
     * Get per‑supplier revenue breakdown (wholesale owed vs commission earned).
     */
    public function supplierBreakdown(Request $request)
    {
        $this->ensureAdmin($request->user());

        $startDate = $request->start_date
            ? now()->parse($request->start_date)->startOfDay()
            : now()->subDays(30)->startOfDay();

        $endDate = $request->end_date
            ? now()->parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        $breakdown = OrderItem::whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->where('payment_status', 'paid')
                      ->whereBetween('payment_confirmed_at', [$startDate, $endDate]);
            })
            ->with('supplier:id,business_name')
            ->select('supplier_id')
            ->selectRaw('SUM(wholesale_cost * quantity) as total_wholesale')
            ->selectRaw('SUM(commission_earned) as total_commission')
            ->groupBy('supplier_id')
            ->get()
            ->map(function ($item) {
                return [
                    'supplier_id'      => $item->supplier_id,
                    'business_name'    => $item->supplier->business_name ?? 'Unknown',
                    'total_wholesale'  => round((float) $item->total_wholesale, 2),
                    'total_commission' => round((float) $item->total_commission, 2),
                ];
            })
            ->sortByDesc('total_commission')
            ->values();

        return response()->json($breakdown);
    }
}