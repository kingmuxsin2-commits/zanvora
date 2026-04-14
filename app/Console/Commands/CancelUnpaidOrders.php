<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CancelUnpaidOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid';
    protected $description = 'Cancel pending payment orders older than 60 minutes and restore stock';

    public function handle()
    {
        $cutoff = now()->subMinutes(60);

        $orders = Order::where('status', 'pending_payment')
            ->where('payment_status', 'pending_manual')
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No unpaid orders to cancel.');
            return;
        }

        $count = 0;
        foreach ($orders as $order) {
            DB::transaction(function () use ($order, &$count) {
                // Restore stock for each order item
                foreach ($order->items as $item) {
                    $item->product->increment('stock_qty', $item->quantity);
                }

                // Update order status
                $order->update([
                    'status' => 'cancelled',
                    'payment_status' => 'cancelled',
                ]);

                $count++;
            });
        }

        $this->info("Cancelled {$count} unpaid orders and restored stock.");
    }
}