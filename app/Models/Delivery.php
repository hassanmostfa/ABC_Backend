<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_method',
        'delivery_address',
        'block',
        'street',
        'house_number',
        'delivery_datetime',
        'received_datetime',
        'delivery_status',
        'notes',
    ];

    protected $casts = [
        'delivery_datetime' => 'datetime',
        'received_datetime' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
