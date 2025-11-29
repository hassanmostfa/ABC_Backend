<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'country_id',
        'governorate_id',
        'area_id',
        'street',
        'house',
        'block',
        'floor',
    ];

    /**
     * Get the customer that owns the address
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the country for the address
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the governorate for the address
     */
    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    /**
     * Get the area for the address
     */
    public function area()
    {
        return $this->belongsTo(Area::class);
    }
}

