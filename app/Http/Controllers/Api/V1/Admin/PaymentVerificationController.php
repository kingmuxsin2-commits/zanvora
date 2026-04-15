<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Order;
use App\Models\Fulfillment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\SupplierNewOrder;

class PaymentVerificationController extends Controller
{
    protected function ensureAdmin($user)
    {
        if (!in_array($user->role, ['admin', 'staff'])) {
            abort(403, 'Unauthorized');
        }
    }

    public function pending(Request $request)
    {
        $this->ensureAdmin($request->user());
        
        $orders = Order::with('customer')
            ->where('payment_status', 'pending_manual')
            ->orderBy('payment_claimed_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return response()->json($orders);
    }
    
    public function confirm(Request $request, Order $order)
    {
        $this->ensureAdmin($request->user());
        
        if ($order->payment_status !== 'pending_manual') {
            return response()->json(['message' => 'Order not awaiting payment'], 400);
        }
        
        DB::transaction(function () use ($order, $request) {
            $order->update([
                'payment_status' => 'paid',
                'status' => 'processing',
                'payment_confirmed_by' => $request->user()->id,
                'payment_confirmed_at' => now(),
            ]);
            
            // Activate fulfillments
            Fulfillment::where('order_id', $order->id)
                ->where('status', 'awaiting_payment')
                ->update(['status' => 'pending']);
                
            // Send email notifications to suppliers
            $fulfillments = Fulfillment::where('order_id', $order->id)
                ->where('status', 'pending')
                ->with('supplier.user')
                ->get();
                
            foreach ($fulfillments as $fulfillment) {
                Mail::to($fulfillment->supplier->user->email)
                    ->queue(new SupplierNewOrder($fulfillment));
            }
        });
        
        return response()->json(['message' => 'Payment confirmed', 'order' => $order->fresh()]);
    }
    
    public function reject(Request $request, Order $order)
    {
        $this->ensureAdmin($request->user());
        
        if ($order->payment_status !== 'pending_manual') {
            return response()->json(['message' => 'Order not awaiting payment'], 400);
        }
        
        DB::transaction(function () use ($order) {
            $order->update([
                'payment_status' => 'cancelled',
                'status' => 'cancelled',
            ]);
            
            // Restore stock (optional)
            foreach ($order->items as $item) {
                $item->product->increment('stock_qty', $item->quantity);
            }
        });
        
        return response()->json(['message' => 'Order cancelled']);
    }
    
    public function markChecking(Request $request, Order $order)
    {
        $this->ensureAdmin($request->user());
        
        $order->update(['admin_checking_at' => now()]);
        
        return response()->json(['message' => 'Status updated']);
    }
}