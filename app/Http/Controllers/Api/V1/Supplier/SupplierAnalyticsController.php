<?php

namespace App\Http\Controllers\Api\V1\Supplier;

use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class SupplierAnalyticsController extends Controller
{
    /**
     * Display analytics for the authenticated supplier.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'supplier') {
            abort(403, 'Unauthorized');
        }

        $supplier = $user->supplier;
        if (!$supplier) {
            return response()->json(['message' => 'Supplier profile not found'], 404);
        }

        $supplierId = $supplier->id;

        // ---------- Sales Summary ----------
        $totalSales = OrderItem::where('supplier_id', $supplierId)
            ->whereHas('order', fn($q) => $q->where('payment_status', 'paid'))
            ->sum(DB::raw('wholesale_cost * quantity'));

        $totalOrders = OrderItem::where('supplier_id', $supplierId)
            ->whereHas('order', fn($q) => $q->where('payment_status', 'paid'))
            ->distinct('order_id')
            ->count('order_id');

        $totalProducts = Product::where('supplier_id', $supplierId)->count();
        $activeProducts = Product::where('supplier_id', $supplierId)
            ->where('status', 'active')
            ->count();

        // ---------- Monthly Sales Trend (last 12 months) ----------
        $monthlySales = OrderItem::where('supplier_id', $supplierId)
            ->whereHas('order', fn($q) => $q->where('payment_status', 'paid'))
            ->selectRaw("DATE_TRUNC('month', order_items.created_at) as month")
            ->selectRaw('SUM(wholesale_cost * quantity) as revenue')
            ->selectRaw('COUNT(DISTINCT order_id) as orders')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->map(fn($item) => [
                'month'   => $item->month,
                'revenue' => round((float) $item->revenue, 2),
                'orders'  => (int) $item->orders,
            ]);

        // ---------- Top Selling Products ----------
        $topProducts = OrderItem::where('supplier_id', $supplierId)
            ->whereHas('order', fn($q) => $q->where('payment_status', 'paid'))
            ->select('product_id')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(wholesale_cost * quantity) as total_revenue')
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->with('product:id,title,images')
            ->get()
            ->map(fn($item) => [
                'id'             => $item->product->id,
                'title'          => $item->product->title,
                'image'          => $item->product->images[0] ?? null,
                'total_quantity' => (int) $item->total_quantity,
                'total_revenue'  => round((float) $item->total_revenue, 2),
            ]);

        // ---------- Customer Repeat Rate (for this supplier) ----------
        $customerIds = Order::where('payment_status', 'paid')
            ->whereHas('items', fn($q) => $q->where('supplier_id', $supplierId))
            ->pluck('customer_id')
            ->unique();

        $repeatCount = 0;
        $totalCustomers = $customerIds->count();
        if ($totalCustomers > 0) {
            $repeatCount = Order::where('payment_status', 'paid')
                ->whereIn('customer_id', $customerIds)
                ->groupBy('customer_id')
                ->havingRaw('COUNT(*) > 1')
                ->count();
        }
        $repeatRate = $totalCustomers > 0 ? round(($repeatCount / $totalCustomers) * 100, 1) : 0;

        // ---------- Inventory Health ----------
        $lowStockThreshold = 5;
        $lowStockCount = Product::where('supplier_id', $supplierId)
            ->where('status', 'active')
            ->where('stock_qty', '<=', $lowStockThreshold)
            ->where('stock_qty', '>', 0)
            ->count();
        $outOfStockCount = Product::where('supplier_id', $supplierId)
            ->where('status', 'active')
            ->where('stock_qty', 0)
            ->count();

        return response()->json([
            'summary' => [
                'total_sales'     => round((float) $totalSales, 2),
                'total_orders'    => $totalOrders,
                'total_products'  => $totalProducts,
                'active_products' => $activeProducts,
            ],
            'monthly_sales'   => $monthlySales,
            'top_products'    => $topProducts,
            'customer_repeat_rate' => $repeatRate,
            'inventory' => [
                'low_stock'    => $lowStockCount,
                'out_of_stock' => $outOfStockCount,
            ],
        ]);
    }
}