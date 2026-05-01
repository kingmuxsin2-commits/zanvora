<?php
namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\DeliveryFee;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DeliveryFeeController extends Controller
{
    protected function ensureAdmin($user) {
        if (!in_array($user->role, ['admin','staff'])) abort(403);
    }

    public function index(Request $request) {
        $this->ensureAdmin($request->user());
        return DeliveryFee::orderBy('degmo')->orderBy('xafad')->get();
    }

    public function store(Request $request) {
        $this->ensureAdmin($request->user());
        $valid = $request->validate([
            'xafad' => 'required|string|max:255',
            'degmo' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);
        $fee = DeliveryFee::create($valid);
        return response()->json($fee, 201);
    }

    public function update(Request $request, DeliveryFee $deliveryFee) {
        $this->ensureAdmin($request->user());
        $valid = $request->validate([
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);
        $deliveryFee->update($valid);
        return response()->json($deliveryFee);
    }

    public function destroy(Request $request, DeliveryFee $deliveryFee) {
        $this->ensureAdmin($request->user());
        $deliveryFee->delete();
        return response()->json(['message' => 'Deleted']);
    }

    /**
     * Public endpoint – return the active delivery fee for a given xafad.
     */
    public function show(Request $request)
    {
        $request->validate(['xafad' => 'required|string']);

        $fee = DeliveryFee::where('xafad', $request->xafad)
            ->where('is_active', true)
            ->first();

        return response()->json([
            'price' => $fee ? (float) $fee->price : 0,
        ]);
    }
}