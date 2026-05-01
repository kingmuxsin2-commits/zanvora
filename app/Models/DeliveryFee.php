<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DeliveryFee extends Model
{
    protected $fillable = ['xafad', 'degmo', 'price', 'is_active'];
    protected $casts = ['price' => 'float', 'is_active' => 'boolean'];
}