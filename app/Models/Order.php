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
        'customer_address_id',
        'order_number',
        'status',
        'total_amount',
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


    /**
     * Get all offers for this order (many-to-many relationship)
     */
    public function offers()
    {
        return $this->belongsToMany(Offer::class, 'order_offers', 'order_id', 'offer_id')
            ->withPivot('quantity')
            ->withTimestamps();
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

    public function customerAddress()
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }
}
