<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fulfillment extends Model
{
    protected $fillable = [
        'order_id', 'supplier_id', 'status', 'tracking_number',
        'carrier', 'shipping_cost', 'shipped_at', 'notes'
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}