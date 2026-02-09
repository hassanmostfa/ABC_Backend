<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    const TYPE_ORDER = 'order';
    const TYPE_WALLET_CHARGE = 'wallet_charge';

    protected $fillable = [
        'invoice_id',
        'customer_id',
        'reference',
        'type',
        'payment_number',
        'gateway',
        'track_id',
        'tran_id',
        'payment_id',
        'receipt_id',
        'amount',
        'bonus_amount',
        'total_amount',
        'method',
        'status',
        'payment_link',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeWalletCharge($query)
    {
        return $query->where('type', self::TYPE_WALLET_CHARGE);
    }
}
