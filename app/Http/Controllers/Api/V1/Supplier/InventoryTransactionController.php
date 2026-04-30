<?php

namespace App\Http\Controllers\Api\V1\Supplier;

use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class InventoryTransactionController extends Controller
{
    /**
     * List all transactions for the authenticated supplier.
     */
    public function index(Request $request)
    {
        $supplier = $request->user()->supplier;
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found'], 404);
        }

        $query = InventoryTransaction::where('supplier_id', $supplier->id);

        if ($request->filled('search')) {
            $query->where('product_name', 'like', '%'.$request->search.'%');
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('transaction_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('transaction_date', '<=', $request->to_date);
        }

        return response()->json($query->orderBy('transaction_date', 'desc')->paginate(20));
    }

    /**
     * Add a new transaction (purchase or sale).
     */
    public function store(Request $request)
    {
        $supplier = $request->user()->supplier;
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found'], 404);
        }

        $validated = $request->validate([
            'type'            => 'required|in:purchase,sale',
            'product_name'    => 'required|string|max:255',
            'price'           => 'required|numeric|min:0',
            'quantity'        => 'integer|min:1',
            'transaction_date'=> 'required|date',
        ]);

        $transaction = InventoryTransaction::create([
            'supplier_id'     => $supplier->id,
            'type'            => $validated['type'],
            'product_name'    => $validated['product_name'],
            'price'           => $validated['price'],
            'quantity'        => $validated['quantity'] ?? 1,
            'transaction_date'=> $validated['transaction_date'],
        ]);

        return response()->json($transaction, 201);
    }

    /**
     * Delete a transaction.
     */
    public function destroy(InventoryTransaction $transaction)
    {
        $supplier = request()->user()->supplier;
        if (!$supplier || $transaction->supplier_id !== $supplier->id) {
            abort(403);
        }

        $transaction->delete();
        return response()->json(['message' => 'Transaction deleted']);
    }

    /**
     * Stock summary – grouped by product, with total buy/sell values.
     */
    public function stockSummary(Request $request)
    {
        $supplier = $request->user()->supplier;
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found'], 404);
        }

        $summary = InventoryTransaction::where('supplier_id', $supplier->id)
            ->select('product_name',
                DB::raw("SUM(CASE WHEN type = 'purchase' THEN quantity ELSE 0 END) as purchased_qty"),
                DB::raw("SUM(CASE WHEN type = 'sale' THEN quantity ELSE 0 END) as sold_qty"),
                DB::raw("SUM(CASE WHEN type = 'purchase' THEN price * quantity ELSE 0 END) as total_buy_value"),
                DB::raw("SUM(CASE WHEN type = 'sale' THEN price * quantity ELSE 0 END) as total_sell_value")
            )
            ->groupBy('product_name')
            ->get()
            ->map(function ($item) {
                $item->current_stock = $item->purchased_qty - $item->sold_qty;
                return $item;
            });

        return response()->json($summary);
    }
}