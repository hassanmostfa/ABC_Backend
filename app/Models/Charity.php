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
        'address',
    ];

    /**
     * Get the offers associated with this charity
     */
    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * Get active offers for this charity
     */
    public function activeOffers()
    {
        return $this->hasMany(Offer::class)->where('is_active', true);
    }
}
