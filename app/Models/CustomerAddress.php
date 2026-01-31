<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    use HasFactory;

    const TYPE_APARTMENT = 'apartment';
    const TYPE_HOUSE = 'house';
    const TYPE_OFFICE = 'office';

    const TYPES = [self::TYPE_APARTMENT, self::TYPE_HOUSE, self::TYPE_OFFICE];

    protected $fillable = [
        'customer_id',
        'country_id',
        'governorate_id',
        'area_id',
        'lat',
        'lng',
        'type',
        'building_name',
        'apartment_number',
        'company',
        'street',
        'house',
        'block',
        'floor',
        'phone_number',
        'additional_directions',
        'address_label',
    ];

    protected $casts = [
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
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

    /**
     * Get formatted address string based on type
     */
    public function getFormattedAddressAttribute(): string
    {
        $parts = [];
        $type = $this->type ?? self::TYPE_HOUSE;

        if ($type === self::TYPE_APARTMENT) {
            if ($this->building_name) $parts[] = $this->building_name;
            if ($this->apartment_number) $parts[] = 'Apt ' . $this->apartment_number;
            if ($this->floor) $parts[] = 'Floor ' . $this->floor;
            if ($this->street) $parts[] = $this->street;
        } elseif ($this->type === self::TYPE_HOUSE) {
            if ($this->house) $parts[] = $this->house;
            if ($this->street) $parts[] = $this->street;
            if ($this->block) $parts[] = 'Block ' . $this->block;
        } elseif ($type === self::TYPE_OFFICE) {
            if ($this->building_name) $parts[] = $this->building_name;
            if ($this->company) $parts[] = $this->company;
            if ($this->floor) $parts[] = 'Floor ' . $this->floor;
            if ($this->street) $parts[] = $this->street;
            if ($this->block) $parts[] = 'Block ' . $this->block;
        }

        if ($this->additional_directions) {
            $parts[] = $this->additional_directions;
        }

        return implode(', ', array_filter($parts)) ?: '—';
    }
}

