<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    public const TYPE_WINBACK = 'winback';
    public const TYPE_TOP_CUSTOMER = 'top_customer';

    protected $fillable = [
        'customer_id',
        'coupon_id',
        'type',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}
