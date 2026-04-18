<?php

namespace App\Http\Controllers\Api\V1\Supplier;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SupplierProfileController extends Controller
{
    /**
     * Update the supplier's store settings.
     */
    public function updateSettings(Request $request)
    {
        $user = $request->user();
        
        if ($user->role !== 'supplier') {
            abort(403, 'Unauthorized');
        }
        
        $supplier = $user->supplier;
        if (!$supplier) {
            return response()->json(['message' => 'Supplier profile not found'], 404);
        }
        
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'address'       => 'required|string',
        ]);
        
        $supplier->update($validated);
        
        return response()->json([
            'message'   => 'Settings updated successfully',
            'supplier'  => $supplier->fresh()
        ]);
    }
}