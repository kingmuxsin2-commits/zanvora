<?php

namespace App\Http\Controllers\Api\V1\Supplier;

use App\Models\InventoryItem;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class InventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $supplier = $request->user()->supplier;
        if (!$supplier) {
            return response()->json(['message' => 'Supplier profile not found'], 404);
        }

        $query = InventoryItem::where('supplier_id', $supplier->id);

        if ($request->filled('search')) {
            $query->where('product_name', 'like', '%' . $request->search . '%');
        }

        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');
        $allowed = ['product_name', 'wholesale_price', 'retail_price', 'initial_stock', 'current_stock', 'created_at'];
        if (in_array($sort, $allowed)) {
            $query->orderBy($sort, $order);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Single endpoint for both create and update.
     * - If `id` is provided → update that row (must belong to supplier).
     * - If `id` is missing → create new row.
     */
    public function store(Request $request)
    {
        $supplier = $request->user()->supplier;
        if (!$supplier) {
            return response()->json(['message' => 'Supplier profile not found'], 404);
        }

        $data = $request->only([
            'id',
            'product_name',
            'wholesale_price',
            'wholesale_price_date',
            'retail_price',
            'retail_price_date',
            'initial_stock',
            'current_stock',
        ]);

        // Numeric casting
        foreach (['wholesale_price','retail_price'] as $f) {
            if (array_key_exists($f, $data)) {
                $data[$f] = $data[$f] === '' || is_null($data[$f]) ? null : (float) $data[$f];
            }
        }
        foreach (['initial_stock','current_stock'] as $f) {
            if (array_key_exists($f, $data)) {
                $data[$f] = is_null($data[$f]) ? 0 : (int) $data[$f];
            }
        }

        // Update existing row?
        if (!empty($data['id'])) {
            $item = InventoryItem::where('id', $data['id'])
                        ->where('supplier_id', $supplier->id)
                        ->first();

            if ($item) {
                unset($data['id']);
                $item->update($data);
                return response()->json($item);
            }
        }

        // Create new row
        unset($data['id']);
        $data['supplier_id'] = $supplier->id;
        if (empty($data['product_name'])) {
            $data['product_name'] = 'New Item';
        }

        $item = InventoryItem::create($data);
        return response()->json($item, 201);
    }

    public function destroy(InventoryItem $item)
    {
        $item->delete();
        return response()->json(['message' => 'Deleted']);
    }
}