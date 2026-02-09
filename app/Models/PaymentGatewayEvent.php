<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentGatewayEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'event_type',
        'track_id',
        'receipt_id',
        'payload',
        'received_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
    ];
}
