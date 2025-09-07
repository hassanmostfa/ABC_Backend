<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Offer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'target_product_id',
        'target_quantity',
        'gift_product_id',
        'gift_quantity',
        'offer_start_date',
        'offer_end_date',
        'is_active',
        'image',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'target_quantity' => 'integer',
        'gift_quantity' => 'integer',
        'offer_start_date' => 'datetime',
        'offer_end_date' => 'datetime',
    ];

    /**
     * Get the target product that the customer must buy
     */
    public function targetProduct()
    {
        return $this->belongsTo(Product::class, 'target_product_id');
    }

    /**
     * Get the gift product that will be given for free
     */
    public function giftProduct()
    {
        return $this->belongsTo(Product::class, 'gift_product_id');
    }

    /**
     * Check if the offer is currently active based on dates and status
     */
    public function isActive(): bool
    {
        $now = Carbon::now();
        return $this->is_active === true 
            && $this->offer_start_date <= $now 
            && $this->offer_end_date >= $now;
    }

    /**
     * Check if the offer is expired
     */
    public function isExpired(): bool
    {
        return Carbon::now() > $this->offer_end_date;
    }

    /**
     * Check if the offer is not yet started
     */
    public function isNotStarted(): bool
    {
        return Carbon::now() < $this->offer_start_date;
    }

    /**
     * Scope to get only active offers
     */
    public function scopeActive($query)
    {
        $now = Carbon::now();
        return $query->where('is_active', true)
                    ->where('offer_start_date', '<=', $now)
                    ->where('offer_end_date', '>=', $now);
    }

    /**
     * Scope to get only expired offers
     */
    public function scopeExpired($query)
    {
        return $query->where('offer_end_date', '<', Carbon::now());
    }

    /**
     * Scope to get only upcoming offers
     */
    public function scopeUpcoming($query)
    {
        return $query->where('offer_start_date', '>', Carbon::now());
    }
}
