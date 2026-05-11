<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\DeliveryFee;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class XaafadController extends Controller
{
    public function index(Request $request)
    {
        $degmo = $request->get('degmo');
        if (!$degmo) {
            return response()->json([]);
        }

        return response()->json(
            DeliveryFee::where('degmo', $degmo)
                ->where('is_active', true)
                ->select('id', 'xafad')
                ->orderBy('xafad')
                ->get()
        );
    }
}