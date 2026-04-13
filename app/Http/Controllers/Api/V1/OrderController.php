<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\Fulfillment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'shipping_address' => 'required|array',
                'shipping_address.name' => 'required|string',
                'shipping_address.phone' => 'required|string',
                'shipping_address.address' => 'required|string',
                'shipping_address.city' => 'required|string',
                'shipping_address.postal_code' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
            ]);

            DB::beginTransaction();
            
            $orderNumber = 'ORD-' . date('Ymd') . '-' . Str::upper(Str::random(4));
            $totalAmount = 0;
            $items = [];

            foreach ($validated['items'] as $itemData) {
                $product = Product::with('supplier')
                    ->where('id', $itemData['product_id'])
                    ->where('status', 'active')
                    ->firstOrFail();

                if ($product->stock_qty < $itemData['quantity']) {
                    return response()->json([
                        'message' => "Insufficient stock for {$product->title}"
                    ], 400);
                }

                $totalAmount += $product->retail_price * $itemData['quantity'];

                $items[] = [
                    'product' => $product,
                    'quantity' => $itemData['quantity'],
                ];
            }

            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_id' => $user->id,
                'total_amount' => $totalAmount,
                'shipping_address' => $validated['shipping_address'],
                'status' => 'pending_payment',
                'payment_method' => 'mobile_money',
                'payment_reference' => $orderNumber,
                'payment_phone' => $validated['shipping_address']['phone'],
                'payment_status' => 'pending_manual',
            ]);

            foreach ($items as $item) {
                $product = $item['product'];
                $quantity = $item['quantity'];

                $product->decrement('stock_qty', $quantity);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->retail_price,
                    'wholesale_cost' => $product->wholesale_price,
                    'supplier_id' => $product->supplier_id,
                    'commission_earned' => $product->retail_price - $product->wholesale_price,
                ]);

                Fulfillment::create([
                    'order_id' => $order->id,
                    'supplier_id' => $product->supplier_id,
                    'status' => 'awaiting_payment',
                    'shipping_cost' => $product->supplier->shipping_flat_fee ?? 0,
                ]);
            }

            DB::commit();

            return response()->json($order->load('items'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}