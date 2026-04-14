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
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentClaimed;

class OrderController extends Controller
{
    /**
     * Store a newly created order.
     */
    public function store(Request $request)
    {
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
        try {
            $orderNumber = 'ORD-' . date('Ymd') . '-' . Str::upper(Str::random(4));
            $totalAmount = 0;
            $items = [];

            foreach ($validated['items'] as $itemData) {
                $product = Product::with('supplier')
                    ->where('id', $itemData['product_id'])
                    ->where('status', 'active')
                    ->firstOrFail();

                if ($product->stock_qty < $itemData['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->title}");
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
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Display the specified order.
     */
    public function show(Request $request, Order $order)
    {
        $user = $request->user();

        // Customers can only view their own orders
        if ($user->role === 'customer' && $order->customer_id !== $user->id) {
            abort(403);
        }

        // Suppliers can view orders containing their items
        if ($user->role === 'supplier') {
            $hasItems = $order->items()->where('supplier_id', $user->supplier->id)->exists();
            if (!$hasItems) {
                abort(403);
            }
        }

        $order->load(['items.product', 'fulfillments.supplier', 'customer']);

        return response()->json($order);
    }

    /**
     * Customer claims payment.
     */
    public function claimPayment(Request $request, Order $order)
    {
        if ($order->customer_id !== $request->user()->id) {
            abort(403);
        }

        if ($order->payment_status !== 'pending_manual') {
            return response()->json(['message' => 'Payment already processed'], 400);
        }

        // Send email to admin
        Mail::to(config('mail.admin_address', env('ADMIN_EMAIL')))
            ->send(new PaymentClaimed($order));

        $order->update(['payment_claimed_at' => now()]);

        return response()->json(['message' => 'Admin notified']);
    }

    /**
     * Get all orders for the authenticated customer.
     */
    public function myOrders(Request $request)
    {
        $user = $request->user();

        $orders = Order::where('customer_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders);
    }

    /**
     * Get orders for the authenticated supplier that are ready to fulfill.
     */
    public function supplierOrders(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'supplier') {
            abort(403, 'Unauthorized');
        }

        $supplier = $user->supplier;
        if (!$supplier) {
            return response()->json(['message' => 'Supplier profile not found'], 404);
        }

        // Get fulfillments that are pending and belong to this supplier
        $fulfillments = Fulfillment::with([
                'order' => function ($query) {
                    $query->select('id', 'order_number', 'customer_id', 'shipping_address', 'status');
                },
                'order.customer' => function ($query) {
                    $query->select('id', 'name', 'phone');
                },
                'order.items' => function ($query) use ($supplier) {
                    $query->where('supplier_id', $supplier->id)
                          ->select('id', 'order_id', 'product_id', 'quantity');
                },
                'order.items.product' => function ($query) {
                    $query->select('id', 'title');
                }
            ])
            ->where('supplier_id', $supplier->id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($fulfillments);
    }
}