<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Order;
use App\Models\User;
use App\Models\CustomerLocation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LocationController extends Controller
{
    protected function ensureAdmin($user)
    {
        if (!in_array($user->role, ['admin', 'staff'])) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * List customers who have placed at least one paid order,
     * with their most recent delivery address and manually saved GPS.
     */
    public function index(Request $request)
    {
        $this->ensureAdmin($request->user());

        // Customers with at least one paid order
        $customers = User::where('role', 'customer')
            ->whereHas('orders', fn($q) => $q->where('payment_status', 'paid'))
            ->withMax('orders as last_order_date', 'created_at')
            ->get();

        // Prepare response
        $data = $customers->map(function ($customer) {
            // Most recent paid order
            $lastOrder = $customer->orders()
                ->where('payment_status', 'paid')
                ->latest('created_at')
                ->first();

            // Extract degmo & xafad from shipping_address.address (format "Degmo – Xafad")
            $address = $lastOrder?->shipping_address['address'] ?? '';
            $parts = explode(' – ', $address);
            $degmo = $parts[0] ?? '';
            $xafad = $parts[1] ?? '';

            // GPS from customer_locations table (if exists)
            $gps = CustomerLocation::where('customer_id', $customer->id)->value('gps');

            return [
                'id'            => $customer->id,
                'name'          => $customer->name,
                'phone'         => $customer->phone,
                'degmo'         => $degmo,
                'xafad'         => $xafad,
                'gps'           => $gps,
                'last_order'    => $lastOrder?->created_at,
            ];
        })->sortByDesc('last_order')->values();

        return response()->json($data);
    }

    /**
     * Update or create the GPS for a customer.
     */
    public function updateGps(Request $request, User $user)
    {
        $this->ensureAdmin($request->user());

        $request->validate(['gps' => 'required|string|max:255']);

        CustomerLocation::updateOrCreate(
            ['customer_id' => $user->id],
            ['gps' => $request->gps]
        );

        return response()->json(['message' => 'GPS saved.']);
    }
}