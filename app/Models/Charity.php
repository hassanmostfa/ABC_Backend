<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Charity extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name_en',
        'name_ar',
        'phone',
        'country_id',
        'governorate_id',
        'area_id',
    ];

    /**
     * Get the offers associated with this charity
     */
    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * Get the country associated with this charity
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the governorate associated with this charity
     */
    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    /**
     * Get the area associated with this charity
     */
    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Get active offers for this charity
     */
    public function activeOffers()
    {
        return $this->hasMany(Offer::class)->where('is_active', true);
    }
}
