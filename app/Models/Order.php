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
        'address',
        'order_number',
        'status',
        'total_amount',
        'offer_snapshot',
        'delivery_type',
        'delivery_date',
        'delivery_time',
        'payment_method',
        'payment_gateway_src',
        'is_sent_to_erp',
        'created_by_id',
        'created_by_type',
    ];


    protected $casts = [
        'total_amount' => 'decimal:2',
        'offer_snapshot' => 'array',
        'delivery_date' => 'date',
        'is_sent_to_erp' => 'boolean',
    ];

    /**
     * Normalize HH:MM to HH:MM:SS for the database time column.
     */
    public function setDeliveryTimeAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['delivery_time'] = null;

            return;
        }
        $s = (string) $value;
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $s)) {
            $this->attributes['delivery_time'] = $s . ':00';

            return;
        }
        $this->attributes['delivery_time'] = $s;
    }

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

    public function customerAddress()
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    /**
     * Get the creator of the order (polymorphic relation: Admin, Customer, User, etc.)
     */
    public function createdBy()
    {
        return $this->morphTo('created_by');
    }
}
