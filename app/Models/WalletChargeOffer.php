<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletChargeOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'charge_amount',
        'get_amount',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'charge_amount' => 'decimal:3',
        'get_amount' => 'decimal:3',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('charge_amount');
    }

    public function bonusAmount(): float
    {
        return round((float) $this->get_amount - (float) $this->charge_amount, 3);
    }
}
