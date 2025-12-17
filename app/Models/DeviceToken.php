<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'token',
    ];

    protected $casts = [
        'customer_id' => 'integer',
    ];

        /**
         * Get the customer that owns the device token
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
