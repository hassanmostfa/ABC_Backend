<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Otp extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'otp_code',
        'otp_type',
        'otp_mode',
        'user_identifier',
        'phone_code',
        'phone',
        'expires_at',
        'is_used',
        'failed_attempts',
        'is_locked',
        'generated_by_ip',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
        'is_locked' => 'boolean',
        'failed_attempts' => 'integer',
    ];

    /**
     * Check if OTP is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if OTP is valid (not used, not expired, and not locked)
     */
    public function isValid(): bool
    {
        return !$this->is_used && !$this->isExpired() && !$this->is_locked;
    }

    /**
     * Increment failed attempts and lock if threshold reached
     */
    public function incrementFailedAttempts(int $lockThreshold = 5): void
    {
        $this->increment('failed_attempts');
        
        if ($this->failed_attempts >= $lockThreshold) {
            $this->update(['is_locked' => true]);
        }
    }
}

