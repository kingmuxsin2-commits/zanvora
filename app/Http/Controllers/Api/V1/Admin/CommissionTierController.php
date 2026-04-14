<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\CommissionTier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CommissionTierController extends Controller
{
    protected function ensureAdmin($user)
    {
        if (!in_array($user->role, ['admin', 'staff'])) {
            abort(403, 'Unauthorized');
        }
    }

    public function index(Request $request)
    {
        $this->ensureAdmin($request->user());
        return CommissionTier::orderBy('min_price')->get();
    }

    public function update(Request $request, CommissionTier $tier)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'min_price' => 'required|numeric|min:0',
            'max_price' => 'required|numeric|min:0',
            'percentage' => 'required|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        $tier->update($validated);
        return response()->json(['message' => 'Tier updated', 'tier' => $tier]);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'min_price' => 'required|numeric|min:0',
            'max_price' => 'required|numeric|min:0',
            'percentage' => 'required|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        $tier = CommissionTier::create($validated);
        return response()->json(['message' => 'Tier created', 'tier' => $tier], 201);
    }

    public function destroy(Request $request, CommissionTier $tier)
    {
        $this->ensureAdmin($request->user());
        $tier->delete();
        return response()->json(['message' => 'Tier deleted']);
    }
}