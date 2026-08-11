<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'offer_id',
        'period',
        'points',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'points' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Available subscription periods in months
     */
    const PERIODS = ['3', '6', '12'];

    /**
     * Get the offer that owns the subscription.
     */
    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Scope to get only active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get period in months as integer
     */
    public function getPeriodInMonthsAttribute(): int
    {
        return (int) $this->period;
    }
}
