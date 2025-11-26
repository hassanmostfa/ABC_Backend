<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'charity_id',
        'order_number',
        'status',
        'total_amount',
        'offer_id',
        'offer_snapshot',
        'delivery_type',
        'payment_method',
    ];


    protected $casts = [
        'total_amount' => 'decimal:2',
        'offer_snapshot' => 'array',
    ];


    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }


    public function charity()
    {
        return $this->belongsTo(Charity::class);
    }


    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }
}
