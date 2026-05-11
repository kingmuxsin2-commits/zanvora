<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\DeliveryFee;
use App\Http\Controllers\Controller;

class DegmoController extends Controller
{
    public function index()
    {
        return response()->json(
            DeliveryFee::select('degmo')
                ->distinct()
                ->orderBy('degmo')
                ->pluck('degmo')
        );
    }
}