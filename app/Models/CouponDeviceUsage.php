<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponDeviceUsage extends Model
{
    protected $fillable = [
        'coupon_id',
        'customer_id',
        'order_id',
        'device_id',
        'device_scope',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * One row per device per coupon. Welcome coupons share a single scope so a
     * new account on the same phone cannot redeem a second welcome code.
     */
    public static function scopeFor(Coupon $coupon): string
    {
        if ($coupon->type === Coupon::TYPE_WELCOME) {
            return 'welcome';
        }

        return 'coupon:'.$coupon->id;
    }
}
