<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointsTransaction extends Model
{
    use HasFactory;

    const TYPE_POINTS_TO_WALLET = 'points_to_wallet';
    const TYPE_POINTS_EARNED = 'points_earned';

    protected $table = 'points_transactions';

    protected $fillable = [
        'customer_id',
        'type',
        'amount',
        'points',
        'description',
        'reference_type',
        'reference_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'points' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the customer that owns the transaction
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the reference model (e.g. Order)
     */
    public function reference()
    {
        return $this->morphTo();
    }
}
