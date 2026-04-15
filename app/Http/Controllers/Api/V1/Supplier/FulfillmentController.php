<?php

namespace App\Http\Controllers\Api\V1\Supplier;

use App\Models\Fulfillment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\CustomerShipment;

class FulfillmentController extends Controller
{
    /**
     * Mark a fulfillment as shipped.
     */
    public function update(Request $request, Fulfillment $fulfillment)
    {
        $user = $request->user();

        // Ensure user is a supplier
        if ($user->role !== 'supplier') {
            abort(403, 'Unauthorized');
        }

        $supplier = $user->supplier;
        if (!$supplier) {
            return response()->json(['message' => 'Supplier profile not found'], 404);
        }

        // Ensure the fulfillment belongs to the authenticated supplier
        if ($fulfillment->supplier_id !== $supplier->id) {
            abort(403, 'Unauthorized');
        }

        // Ensure fulfillment is in 'pending' status
        if ($fulfillment->status !== 'pending') {
            return response()->json(['message' => 'Fulfillment cannot be marked as shipped'], 400);
        }

        $validated = $request->validate([
            'carrier' => 'required|string|max:50',
            'tracking_number' => 'required|string|max:100',
        ]);

        DB::transaction(function () use ($fulfillment, $validated) {
            $fulfillment->update([
                'status' => 'shipped',
                'carrier' => $validated['carrier'],
                'tracking_number' => $validated['tracking_number'],
                'shipped_at' => now(),
            ]);

            // Check if all fulfillments for this order are shipped
            $order = $fulfillment->order;
            $allShipped = $order->fulfillments()->where('status', '!=', 'shipped')->doesntExist();

            if ($allShipped) {
                $order->update(['status' => 'shipped']);
            } else {
                $order->update(['status' => 'partial_shipped']);
            }
        });

        // Send email notification to customer with tracking info (queued)
        Mail::to($fulfillment->order->customer->email)
            ->queue(new CustomerShipment($fulfillment));

        return response()->json(['message' => 'Fulfillment marked as shipped', 'fulfillment' => $fulfillment->fresh()]);
    }
}