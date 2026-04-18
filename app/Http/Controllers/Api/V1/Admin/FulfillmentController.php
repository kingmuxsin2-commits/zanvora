<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Fulfillment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class FulfillmentController extends Controller
{
    protected function ensureAdmin($user)
    {
        if (!in_array($user->role, ['admin', 'staff'])) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Mark a fulfillment as delivered.
     */
    public function markDelivered(Request $request, Fulfillment $fulfillment)
    {
        $this->ensureAdmin($request->user());

        if ($fulfillment->status !== 'shipped') {
            return response()->json(['message' => 'Only shipped fulfillments can be marked as delivered.'], 400);
        }

        DB::transaction(function () use ($fulfillment) {
            $fulfillment->update(['status' => 'delivered']);

            $order = $fulfillment->order;
            // If all fulfillments are delivered, update order status to 'delivered'
            $allDelivered = $order->fulfillments()->where('status', '!=', 'delivered')->doesntExist();
            if ($allDelivered) {
                $order->update(['status' => 'delivered']);
            }
        });

        return response()->json(['message' => 'Fulfillment marked as delivered.', 'fulfillment' => $fulfillment->fresh()]);
    }
}