<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\CommissionTier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CommissionTierController extends Controller
{
    /**
     * Ensure the user is an admin (staff not allowed).
     */
    protected function ensureAdmin($user)
    {
        if ($user->role !== 'admin') {
            abort(403, 'Only administrators can manage commission tiers.');
        }
    }

    /**
     * Check if a price range overlaps with any existing tier.
     */
    private function hasOverlap($min, $max, $excludeId = null)
    {
        $query = CommissionTier::where('min_price', '<=', $max)
            ->where('max_price', '>=', $min);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function index(Request $request)
    {
        $this->ensureAdmin($request->user());
        return CommissionTier::orderBy('min_price')->get();
    }

    public function store(Request $request)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'min_price' => 'required|numeric|min:0',
            'max_price' => 'required|numeric|min:0|gt:min_price',
            'percentage' => 'required|numeric|min:0|max:100',
            'is_active'  => 'boolean',
        ]);

        if ($this->hasOverlap($validated['min_price'], $validated['max_price'])) {
            return response()->json([
                'errors' => ['min_price' => ['This price range overlaps with an existing tier.']]
            ], 422);
        }

        $tier = CommissionTier::create($validated);
        return response()->json(['message' => 'Tier created', 'tier' => $tier], 201);
    }

    public function update(Request $request, CommissionTier $tier)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'min_price' => 'required|numeric|min:0',
            'max_price' => 'required|numeric|min:0|gt:min_price',
            'percentage' => 'required|numeric|min:0|max:100',
            'is_active'  => 'boolean',
        ]);

        if ($this->hasOverlap($validated['min_price'], $validated['max_price'], $tier->id)) {
            return response()->json([
                'errors' => ['min_price' => ['This price range overlaps with an existing tier.']]
            ], 422);
        }

        $tier->update($validated);
        return response()->json(['message' => 'Tier updated', 'tier' => $tier]);
    }

    public function destroy(Request $request, CommissionTier $tier)
    {
        $this->ensureAdmin($request->user());
        $tier->delete();
        return response()->json(['message' => 'Tier deleted']);
    }
}