<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_id',
        'total_amount',
        'shipping_address',
        'status',
        'payment_method',
        'payment_reference',
        'payment_reference_override',
        'payment_phone',
        'payment_status',
        'payment_confirmed_by',
        'payment_confirmed_at',
        'payment_claimed_at',
        'admin_checking_at',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'payment_confirmed_at' => 'datetime',
        'payment_claimed_at' => 'datetime',
        'admin_checking_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_confirmed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    /**
     * Get the credits associated with this order.
     */
    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }
}