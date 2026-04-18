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
        'supplier_id',
        'title',
        'description',
        'wholesale_price',
        'retail_price',
        'stock_qty',
        'images',
        'video_url',        
        'variants',
        'status'
    ];

    protected $casts = [
        'images' => 'array',
        'variants' => 'array',
    ];

    /**
     * Append computed rating fields to the model's JSON form.
     */
    protected $appends = ['average_rating', 'total_reviews'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get all ratings for this product.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Calculate the average rating (rounded to 1 decimal).
     */
    public function getAverageRatingAttribute(): float
    {
        return round($this->ratings()->avg('rating') ?? 0, 1);
    }

    /**
     * Get the total number of ratings.
     */
    public function getTotalReviewsAttribute(): int
    {
        return $this->ratings()->count();
    }

    /**
     * Get the indexable data array for Meilisearch.
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