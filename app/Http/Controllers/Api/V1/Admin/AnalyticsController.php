<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\InventoryTransaction;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    protected function ensureAdmin($user)
    {
        if ($user->role !== 'admin') {
            abort(403, 'Only administrators can access analytics.');
        }
    }

    // ---------- CUSTOMER ANALYTICS ----------
    public function index(Request $request)
    {
        $this->ensureAdmin($request->user());

        $userStats = [
            'total_customers' => User::where('role', 'customer')->count(),
            'total_suppliers' => User::where('role', 'supplier')->count(),
            'total_admins'    => User::whereIn('role', ['admin', 'staff'])->count(),
        ];

        $signups = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $orders = Order::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $totalCustomers = User::where('role', 'customer')->count();
        $repeatCustomers = User::where('role', 'customer')
            ->whereHas('orders', fn($q) => $q->where('payment_status', 'paid'), '>', 1)
            ->count();
        $repeatRate = $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 1) : 0;

        $paidOrdersCount = Order::where('payment_status', 'paid')->count();
        $conversionRate = $totalCustomers > 0 ? round(($paidOrdersCount / $totalCustomers) * 100, 1) : 0;

        $lifetimes = DB::table('orders')
            ->select('customer_id')
            ->selectRaw('MIN(created_at) as first_order, MAX(created_at) as last_order')
            ->where('payment_status', 'paid')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) >= 1')
            ->get()
            ->map(function ($row) {
                $first = now()->parse($row->first_order);
                $last  = now()->parse($row->last_order);
                $days  = $last->diffInDays($first);
                return $days >= 0 ? $days : 0;
            });
        $avgLifetime = $lifetimes->isNotEmpty() ? round($lifetimes->avg(), 1) : 0;

        $thirtyDaysAgo = now()->subDays(30);
        $returningCustomers = DB::table('orders')
            ->select('customer_id')
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->whereExists(function ($query) use ($thirtyDaysAgo) {
                $query->select(DB::raw(1))
                      ->from('orders as o2')
                      ->whereColumn('o2.customer_id', 'orders.customer_id')
                      ->where('o2.payment_status', 'paid')
                      ->where('o2.created_at', '<', $thirtyDaysAgo);
            })
            ->distinct()
            ->count('customer_id');

        return response()->json([
            'user_stats'              => $userStats,
            'signups'                 => $signups,
            'orders'                  => $orders,
            'repeat_rate'             => $repeatRate,
            'conversion_rate'         => $conversionRate,
            'total_orders'            => Order::count(),
            'paid_orders'             => $paidOrdersCount,
            'avg_lifetime_days'       => $avgLifetime,
            'returning_customers_30d' => $returningCustomers,
        ]);
    }

    public function cohortRetention(Request $request)
    {
        $this->ensureAdmin($request->user());

        $cohorts = User::where('role', 'customer')
            ->selectRaw("DATE_TRUNC('month', created_at) as cohort_month, id")
            ->get()
            ->groupBy('cohort_month');

        $result = [];
        $maxMonths = 6;

        foreach ($cohorts as $cohortMonth => $users) {
            $cohortDate = now()->parse($cohortMonth);
            $customerIds = $users->pluck('id')->toArray();
            $totalInCohort = count($customerIds);

            $retention = [];
            for ($i = 0; $i <= $maxMonths; $i++) {
                $targetMonth = $cohortDate->copy()->addMonths($i);
                if ($targetMonth->greaterThan(now())) break;

                $activeCount = Order::whereIn('customer_id', $customerIds)
                    ->where('payment_status', 'paid')
                    ->whereYear('created_at', $targetMonth->year)
                    ->whereMonth('created_at', $targetMonth->month)
                    ->distinct('customer_id')
                    ->count('customer_id');

                $retention[$i] = $totalInCohort > 0
                    ? round(($activeCount / $totalInCohort) * 100, 1)
                    : 0;
            }

            $result[] = [
                'cohort'    => $cohortDate->format('M Y'),
                'size'      => $totalInCohort,
                'retention' => $retention,
            ];
        }

        usort($result, fn($a, $b) => strtotime($a['cohort']) - strtotime($b['cohort']));
        return response()->json($result);
    }

    public function rfmAnalysis(Request $request)
    {
        $this->ensureAdmin($request->user());

        $customers = User::where('role', 'customer')->get();
        $now = now();

        $rfmData = $customers->map(function ($customer) use ($now) {
            $orders = $customer->orders()->where('payment_status', 'paid')->get();
            if ($orders->isEmpty()) return null;

            $lastOrder = $orders->max('created_at');
            $recency = $now->diffInDays($lastOrder);
            $frequency = $orders->count();
            $monetary = $orders->sum('total_amount');

            return [
                'customer_id' => $customer->id,
                'recency'     => $recency,
                'frequency'   => $frequency,
                'monetary'    => round((float) $monetary, 2),
            ];
        })->filter()->values();

        if ($rfmData->isNotEmpty()) {
            $rQuartiles = $this->getQuartiles($rfmData->pluck('recency')->toArray());
            $fQuartiles = $this->getQuartiles($rfmData->pluck('frequency')->toArray());
            $mQuartiles = $this->getQuartiles($rfmData->pluck('monetary')->toArray());

            $rfmData = $rfmData->map(function ($item) use ($rQuartiles, $fQuartiles, $mQuartiles) {
                $item['r_score'] = $this->getQuartileScore($item['recency'], $rQuartiles, true);
                $item['f_score'] = $this->getQuartileScore($item['frequency'], $fQuartiles, false);
                $item['m_score'] = $this->getQuartileScore($item['monetary'], $mQuartiles, false);
                $item['rfm_score'] = $item['r_score'] . $item['f_score'] . $item['m_score'];
                return $item;
            });
        }

        $segments = [
            'VIP'     => $rfmData->filter(fn($c) => ($c['r_score'] ?? 0) >= 3 && ($c['f_score'] ?? 0) >= 3 && ($c['m_score'] ?? 0) >= 3)->count(),
            'Loyal'   => $rfmData->filter(fn($c) => ($c['f_score'] ?? 0) >= 3)->count(),
            'At Risk' => $rfmData->filter(fn($c) => ($c['r_score'] ?? 0) <= 2 && ($c['f_score'] ?? 0) >= 3)->count(),
            'New'     => $rfmData->filter(fn($c) => ($c['r_score'] ?? 0) >= 3 && ($c['f_score'] ?? 0) <= 1)->count(),
            'Lost'    => $rfmData->filter(fn($c) => ($c['r_score'] ?? 0) <= 1 && ($c['f_score'] ?? 0) <= 1)->count(),
        ];

        return response()->json([
            'segments' => $segments,
            'top_vip'  => $rfmData->sortByDesc('monetary')->take(10)->values(),
            'rfm_data' => $rfmData,
        ]);
    }

    public function ltvDistribution(Request $request)
    {
        $this->ensureAdmin($request->user());

        $customers = User::where('role', 'customer')->get();
        $ltvData = $customers->map(fn($c) => round((float) $c->orders()->where('payment_status', 'paid')->sum('total_amount'), 2))
            ->filter(fn($v) => $v > 0)->values();

        if ($ltvData->isEmpty()) {
            return response()->json(['buckets' => [], 'average_ltv' => 0, 'total_customers_with_purchases' => 0]);
        }

        $buckets = [
            '$0 - $50'    => $ltvData->filter(fn($v) => $v <= 50)->count(),
            '$50 - $100'  => $ltvData->filter(fn($v) => $v > 50 && $v <= 100)->count(),
            '$100 - $250' => $ltvData->filter(fn($v) => $v > 100 && $v <= 250)->count(),
            '$250 - $500' => $ltvData->filter(fn($v) => $v > 250 && $v <= 500)->count(),
            '$500+'       => $ltvData->filter(fn($v) => $v > 500)->count(),
        ];

        return response()->json([
            'buckets'                      => $buckets,
            'average_ltv'                  => round($ltvData->avg(), 2),
            'total_customers_with_purchases' => $ltvData->count(),
        ]);
    }

    // ---------- SUPPLIER ANALYTICS ----------
    public function supplierAnalytics(Request $request)
    {
        $this->ensureAdmin($request->user());

        $totalApprovedSuppliers = Supplier::where('is_approved', true)->count();
        $totalProducts = Product::whereHas('supplier', fn($q) => $q->where('is_approved', true))->count();

        $topSellers = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('suppliers', 'order_items.supplier_id', '=', 'suppliers.id')
            ->where('orders.payment_status', 'paid')
            ->where('suppliers.is_approved', true)
            ->select('suppliers.id', 'suppliers.business_name')
            ->selectRaw('COALESCE(SUM(order_items.wholesale_cost * order_items.quantity), 0) as total_revenue')
            ->groupBy('suppliers.id', 'suppliers.business_name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->map(fn($item) => [
                'id'             => $item->id,
                'business_name'  => $item->business_name,
                'total_revenue'  => round((float) $item->total_revenue, 2),
            ]);

        $topListers = DB::table('products')
            ->join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.is_approved', true)
            ->where('products.status', 'active')
            ->select('suppliers.id', 'suppliers.business_name')
            ->selectRaw('COUNT(products.id) as product_count')
            ->groupBy('suppliers.id', 'suppliers.business_name')
            ->orderByDesc('product_count')
            ->limit(10)
            ->get()
            ->map(fn($item) => [
                'id'             => $item->id,
                'business_name'  => $item->business_name,
                'product_count'  => (int) $item->product_count,
            ]);

        $productDistribution = DB::table('products')
            ->join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.is_approved', true)
            ->select('suppliers.id')
            ->selectRaw('COUNT(products.id) as product_count')
            ->groupBy('suppliers.id')
            ->get();

        $buckets = [
            '0-5'   => $productDistribution->filter(fn($s) => $s->product_count <= 5)->count(),
            '6-10'  => $productDistribution->filter(fn($s) => $s->product_count > 5 && $s->product_count <= 10)->count(),
            '11-20' => $productDistribution->filter(fn($s) => $s->product_count > 10 && $s->product_count <= 20)->count(),
            '20+'   => $productDistribution->filter(fn($s) => $s->product_count > 20)->count(),
        ];

        $monthlyProducts = Product::selectRaw("DATE_TRUNC('month', created_at) as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->map(fn($item) => ['month' => $item->month, 'count' => (int) $item->count]);

        return response()->json([
            'total_approved_suppliers' => $totalApprovedSuppliers,
            'total_products'           => $totalProducts,
            'top_sellers'              => $topSellers,
            'top_listers'              => $topListers,
            'product_buckets'          => $buckets,
            'monthly_products'         => $monthlyProducts,
        ]);
    }

    public function supplierCohortRetention(Request $request)
    {
        $this->ensureAdmin($request->user());

        $cohorts = Supplier::where('is_approved', true)
            ->selectRaw("DATE_TRUNC('month', created_at) as cohort_month, id")
            ->get()
            ->groupBy('cohort_month');

        $result = [];
        $maxMonths = 6;

        foreach ($cohorts as $cohortMonth => $suppliers) {
            $cohortDate = now()->parse($cohortMonth);
            $supplierIds = $suppliers->pluck('id')->toArray();
            $totalInCohort = count($supplierIds);

            $retention = [];
            for ($i = 0; $i <= $maxMonths; $i++) {
                $targetMonth = $cohortDate->copy()->addMonths($i);
                if ($targetMonth->greaterThan(now())) break;

                $activeCount = Product::whereIn('supplier_id', $supplierIds)
                    ->whereYear('created_at', $targetMonth->year)
                    ->whereMonth('created_at', $targetMonth->month)
                    ->distinct('supplier_id')
                    ->count('supplier_id');

                $retention[$i] = $totalInCohort > 0
                    ? round(($activeCount / $totalInCohort) * 100, 1)
                    : 0;
            }

            $result[] = [
                'cohort'    => $cohortDate->format('M Y'),
                'size'      => $totalInCohort,
                'retention' => $retention,
            ];
        }

        usort($result, fn($a, $b) => strtotime($a['cohort']) - strtotime($b['cohort']));
        return response()->json($result);
    }

    public function arpsTrend(Request $request)
    {
        $this->ensureAdmin($request->user());

        $monthlyRevenue = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.payment_status', 'paid')
            ->selectRaw("DATE_TRUNC('month', orders.created_at) as month")
            ->selectRaw('COALESCE(SUM(order_items.wholesale_cost * order_items.quantity), 0) as total_revenue')
            ->selectRaw('COUNT(DISTINCT order_items.supplier_id) as active_suppliers')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->map(fn($row) => [
                'month' => $row->month,
                'arps'  => $row->active_suppliers > 0
                    ? round($row->total_revenue / $row->active_suppliers, 2)
                    : 0,
            ]);

        return response()->json($monthlyRevenue);
    }

    public function revenueConcentration(Request $request)
    {
        $this->ensureAdmin($request->user());

        $supplierRevenues = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.payment_status', 'paid')
            ->select('supplier_id')
            ->selectRaw('COALESCE(SUM(order_items.wholesale_cost * order_items.quantity), 0) as revenue')
            ->groupBy('supplier_id')
            ->orderByDesc('revenue')
            ->get();

        $totalRevenue = $supplierRevenues->sum('revenue');
        $totalSuppliers = $supplierRevenues->count();

        if ($totalSuppliers == 0 || $totalRevenue == 0) {
            return response()->json(['top_20_count' => 0, 'top_20_revenue_share' => 0, 'total_suppliers_with_revenue' => 0]);
        }

        $top20Count = max(1, (int) ceil($totalSuppliers * 0.2));
        $top20Revenue = $supplierRevenues->take($top20Count)->sum('revenue');
        $share = round(($top20Revenue / $totalRevenue) * 100, 1);

        return response()->json([
            'top_20_count'               => $top20Count,
            'top_20_revenue_share'       => $share,
            'total_suppliers_with_revenue' => $totalSuppliers,
        ]);
    }

    public function supplierChurn(Request $request)
    {
        $this->ensureAdmin($request->user());

        $ninetyDaysAgo = now()->subDays(90);

        // Active suppliers: have products added or have orders in last 90 days
        $activeQuery = Supplier::where('is_approved', true)
            ->where(function ($query) use ($ninetyDaysAgo) {
                $query->whereHas('products', fn($q) => $q->where('created_at', '>=', $ninetyDaysAgo))
                      ->orWhereIn('id', function ($sub) use ($ninetyDaysAgo) {
                          $sub->select('supplier_id')
                              ->from('order_items')
                              ->join('orders', 'order_items.order_id', '=', 'orders.id')
                              ->where('orders.payment_status', 'paid')
                              ->where('orders.created_at', '>=', $ninetyDaysAgo)
                              ->distinct();
                      });
            });

        $active = $activeQuery->count();
        $total = Supplier::where('is_approved', true)->count();
        $churned = $total - $active;

        return response()->json([
            'churned'    => $churned,
            'active'     => $active,
            'total'      => $total,
            'churn_rate' => $total > 0 ? round(($churned / $total) * 100, 1) : 0,
        ]);
    }

    // ---------- PRODUCT ANALYTICS ----------
    public function productAnalytics(Request $request)
    {
        $this->ensureAdmin($request->user());

        $totalProducts = Product::count();

        $statusCounts = Product::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $topByQuantity = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.payment_status', 'paid')
            ->select('products.id', 'products.title', 'products.created_at')
            ->selectRaw('SUM(order_items.quantity)::integer as total_quantity')
            ->selectRaw('MIN(orders.created_at) as first_sale')
            ->groupBy('products.id', 'products.title', 'products.created_at')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get()
            ->map(function ($p) {
                $daysSinceListed = now()->diffInDays($p->created_at) ?: 1;
                $velocity = round($p->total_quantity / $daysSinceListed, 2);
                return [
                    'id'             => $p->id,
                    'title'          => $p->title,
                    'total_quantity' => (int) $p->total_quantity,
                    'velocity'       => $velocity,
                ];
            });

        $topByRevenue = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.payment_status', 'paid')
            ->select('products.id', 'products.title')
            ->selectRaw('COALESCE(SUM(order_items.wholesale_cost * order_items.quantity), 0) as total_revenue')
            ->groupBy('products.id', 'products.title')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'id'            => $p->id,
                'title'         => $p->title,
                'total_revenue' => round((float) $p->total_revenue, 2),
            ]);

        $unsoldCount = Product::where('status', 'active')
            ->whereDoesntHave('orderItems', fn($q) => $q->whereHas('order', fn($o) => $o->where('payment_status', 'paid')))
            ->count();

        $stockDistribution = Product::select('stock_qty')
            ->where('status', 'active')
            ->get()
            ->mapToGroups(function ($product) {
                $qty = $product->stock_qty;
                if ($qty == 0) return ['0' => 1];
                if ($qty <= 5) return ['1-5' => 1];
                if ($qty <= 20) return ['6-20' => 1];
                if ($qty <= 100) return ['21-100' => 1];
                return ['100+' => 1];
            })
            ->map(fn($group) => $group->count())
            ->toArray();

        $buckets = ['0' => 0, '1-5' => 0, '6-20' => 0, '21-100' => 0, '100+' => 0];
        foreach ($stockDistribution as $range => $count) {
            $buckets[$range] = $count;
        }

        $priceDistribution = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.payment_status', 'paid')
            ->select('order_items.unit_price')
            ->get()
            ->mapToGroups(function ($item) {
                $price = (float) $item->unit_price;
                if ($price < 10) return ['Under $10' => 1];
                if ($price < 25) return ['$10 - $25' => 1];
                if ($price < 50) return ['$25 - $50' => 1];
                if ($price < 100) return ['$50 - $100' => 1];
                return ['$100+' => 1];
            })
            ->map(fn($group) => $group->count())
            ->toArray();

        $priceBuckets = ['Under $10' => 0, '$10 - $25' => 0, '$25 - $50' => 0, '$50 - $100' => 0, '$100+' => 0];
        foreach ($priceDistribution as $range => $count) {
            $priceBuckets[$range] = $count;
        }

        $monthlyProducts = Product::selectRaw("DATE_TRUNC('month', created_at) as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->map(fn($item) => ['month' => $item->month, 'count' => (int) $item->count]);

        $monthlySales = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.payment_status', 'paid')
            ->selectRaw("DATE_TRUNC('month', orders.created_at) as month")
            ->selectRaw('SUM(order_items.quantity)::integer as total_quantity')
            ->selectRaw('COALESCE(SUM(order_items.wholesale_cost * order_items.quantity), 0) as total_revenue')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->map(fn($item) => [
                'month'    => $item->month,
                'quantity' => (int) $item->total_quantity,
                'revenue'  => round((float) $item->total_revenue, 2),
            ]);

        return response()->json([
            'total_products'   => $totalProducts,
            'status_counts'    => $statusCounts,
            'top_by_quantity'  => $topByQuantity,
            'top_by_revenue'   => $topByRevenue,
            'unsold_count'     => $unsoldCount,
            'stock_buckets'    => $buckets,
            'price_buckets'    => $priceBuckets,
            'monthly_products' => $monthlyProducts,
            'monthly_sales'    => $monthlySales,
        ]);
    }

    // ---------- INVENTORY ANALYTICS ----------
    public function inventoryAnalytics(Request $request)
    {
        $this->ensureAdmin($request->user());

        $supplierId = $request->get('supplier_id');
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate   = $request->get('end_date', now()->toDateString());

        // Base query for transactions within date range
        $query = InventoryTransaction::whereBetween('transaction_date', [$startDate, $endDate]);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        // 1. Summary stats
        $totalTransactions = (clone $query)->count();
        $totalSuppliers    = (clone $query)->distinct('supplier_id')->count('supplier_id');
        $totalBuyValue     = (clone $query)->where('type', 'purchase')->sum(DB::raw('price * quantity'));
        $totalSellValue    = (clone $query)->where('type', 'sale')->sum(DB::raw('price * quantity'));
        $avgMargin = $totalSellValue - $totalBuyValue;

        // 2. Daily trend (last 30 days)
        $dailyTrend = InventoryTransaction::whereBetween('transaction_date', [$startDate, $endDate])
            ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
            ->select(
                DB::raw('DATE(transaction_date) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => ['date' => $r->date, 'count' => (int)$r->count]);

        // 3. Per‑supplier breakdown
        $supplierBreakdown = InventoryTransaction::whereBetween('transaction_date', [$startDate, $endDate])
            ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
            ->with('supplier:id,business_name')
            ->get()
            ->groupBy('supplier_id')
            ->map(function ($transactions, $supplierId) {
                $supplier = $transactions->first()->supplier;
                $purchase = $transactions->where('type', 'purchase');
                $sale     = $transactions->where('type', 'sale');
                $totalBuy  = $purchase->sum(fn($t) => $t->price * $t->quantity);
                $totalSell = $sale->sum(fn($t) => $t->price * $t->quantity);
                return [
                    'supplier_id'    => $supplierId,
                    'business_name'  => $supplier->business_name ?? 'Unknown',
                    'transaction_count' => $transactions->count(),
                    'last_activity'  => $transactions->max('transaction_date'),
                    'total_buy_value'   => round($totalBuy, 2),
                    'total_sell_value'  => round($totalSell, 2),
                    'margin'         => round($totalSell - $totalBuy, 2),
                ];
            })
            ->values();

        // 4. Frequency analysis (average transactions per day per supplier)
        $daysDiff = max(1, now()->parse($startDate)->diffInDays($endDate) ?: 1);
        $freqAnalysis = $supplierBreakdown->map(function ($supplier) use ($daysDiff) {
            $supplier['avg_transactions_per_day'] = round($supplier['transaction_count'] / $daysDiff, 1);
            return $supplier;
        });

        return response()->json([
            'summary' => [
                'total_transactions' => $totalTransactions,
                'total_suppliers'    => $totalSuppliers,
                'total_buy_value'    => round($totalBuyValue, 2),
                'total_sell_value'   => round($totalSellValue, 2),
                'avg_margin'         => round($avgMargin, 2),
            ],
            'daily_trend'        => $dailyTrend,
            'supplier_breakdown' => $freqAnalysis,
        ]);
    }

    // ---------- REGION ANALYTICS ----------
    public function regionAnalytics(Request $request)
    {
        $this->ensureAdmin($request->user());

        // Total orders per Degmo (extracted from shipping_address->address field)
        $orders = Order::where('payment_status', 'paid')->get();

        $regionCounts = $orders->map(function ($order) {
            $address = $order->shipping_address['address'] ?? '';
            // Extract Degmo – assume format "Degmo – Xafad" or just the first part before " – "
            $degmo = explode(' – ', $address)[0] ?? 'Unknown';
            return trim($degmo);
        })->countBy()->sortDesc()->take(10);

        // Monthly orders per region (top 5 regions over last 12 months)
        $topRegions = $regionCounts->keys()->take(5)->toArray();

        $monthlyRegionData = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            $monthData = ['month' => $month];

            foreach ($topRegions as $region) {
                $count = Order::where('payment_status', 'paid')
                    ->whereYear('created_at', now()->subMonths($i)->year)
                    ->whereMonth('created_at', now()->subMonths($i)->month)
                    ->where('shipping_address->address', 'LIKE', "$region%")
                    ->count();
                $monthData[$region] = $count;
            }
            $monthlyRegionData[] = $monthData;
        }

        return response()->json([
            'top_regions' => $regionCounts,
            'monthly_region_data' => $monthlyRegionData,
        ]);
    }

    // ---------- HELPERS ----------
    private function getQuartiles(array $data): array
    {
        sort($data);
        $count = count($data);
        if ($count === 0) return [0, 0, 0];
        return [
            $data[(int) floor($count * 0.25)] ?? $data[0],
            $data[(int) floor($count * 0.5)] ?? $data[0],
            $data[(int) floor($count * 0.75)] ?? $data[0],
        ];
    }

    private function getQuartileScore($value, array $quartiles, bool $inverse): int
    {
        if ($value <= $quartiles[0]) return $inverse ? 4 : 1;
        if ($value <= $quartiles[1]) return $inverse ? 3 : 2;
        if ($value <= $quartiles[2]) return $inverse ? 2 : 3;
        return $inverse ? 1 : 4;
    }
}