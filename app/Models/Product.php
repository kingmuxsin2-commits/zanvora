<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable;

    protected $fillable = [
        'supplier_id', 'title', 'description', 'wholesale_price',
        'retail_price', 'stock_qty', 'images', 'variants', 'status'
    ];

    protected $casts = [
        'images' => 'array',
        'variants' => 'array',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'retail_price' => (float) $this->retail_price,
            'status' => $this->status,
            'supplier_name' => $this->supplier->business_name ?? '',
        ];
    }
}