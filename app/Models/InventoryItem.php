<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    protected $fillable = [
        'supplier_id',
        'product_name',
        'wholesale_price',
        'wholesale_price_date',
        'retail_price',
        'retail_price_date',
        'initial_stock',
        'current_stock',
    ];

    protected $casts = [
        'wholesale_price_date' => 'date',
        'retail_price_date'   => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Return only the date portion (Y-m-d) for the wholesale price date.
     */
    public function getWholesalePriceDateAttribute($value)
    {
        return $value ? date('Y-m-d', strtotime($value)) : null;
    }

    /**
     * Return only the date portion (Y-m-d) for the retail price date.
     */
    public function getRetailPriceDateAttribute($value)
    {
        return $value ? date('Y-m-d', strtotime($value)) : null;
    }
}