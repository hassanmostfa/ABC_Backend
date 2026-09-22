<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceVoucher extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const METHOD_WALLET = 'wallet';

    public const METHOD_ONLINE = 'online';

    protected $fillable = [
        'customer_id',
        'service_id',
        'service_checkout_id',
        'payment_id',
        'service_name',
        'code',
        'amount',
        'payment_method',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(ServiceCheckout::class, 'service_checkout_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
