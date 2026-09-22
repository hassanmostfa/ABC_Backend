<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    public static string $STORAGE_DIR = 'images/services';

    protected $fillable = [
        'name',
        'image',
        'price',
        'service_provider_email',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function vouchers(): HasMany
    {
        return $this->hasMany(ServiceVoucher::class);
    }

    public function checkouts(): HasMany
    {
        return $this->hasMany(ServiceCheckout::class);
    }
}
