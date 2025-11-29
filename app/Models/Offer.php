<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\ManagesFileUploads;
use Carbon\Carbon;

class Offer extends Model
{
    use HasFactory, ManagesFileUploads;

    static string $STORAGE_DIR = "images/offers";

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
        'offer_start_date',
        'offer_end_date',
        'is_active',
        'image',
        'type',
        'points',
        'charity_id',
        'reward_type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'offer_start_date' => 'datetime',
        'offer_end_date' => 'datetime',
        'is_active' => 'boolean',
        'points' => 'integer',
    ];

    /**
     * Get the offer conditions (target products)
     */
    public function conditions()
    {
        return $this->hasMany(OfferCondition::class);
    }

    /**
     * Get the offer rewards (gift products)
     */
    public function rewards()
    {
        return $this->hasMany(OfferReward::class);
    }

    /**
     * Get active conditions only
     */
    public function activeConditions()
    {
        return $this->hasMany(OfferCondition::class)->where('is_active', true);
    }

    /**
     * Get active rewards only
     */
    public function activeRewards()
    {
        return $this->hasMany(OfferReward::class)->where('is_active', true);
    }

    /**
     * Get the charity associated with this offer (if any)
     */
    public function charity()
    {
        return $this->belongsTo(Charity::class);
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

    /**
     * Get the image URL for this offer
     */
    public function getImageUrlAttribute(): string
    {
        return $this->getFileUrl($this->image, 'public', 'no-image.png');
    }

    /**
     * Delete the offer's image file
     */
    public function deleteImage(): bool
    {
        if ($this->image) {
            return $this->deleteFile($this->image, 'public');
        }
        return false;
    }
}
