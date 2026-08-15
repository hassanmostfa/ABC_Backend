<?php

namespace App\Models;

use App\Traits\ManagesFileUploads;
use App\Support\KuwaitPhone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, ManagesFileUploads;

    static string $STORAGE_DIR = "images/customers/profile";

    protected $fillable = [
        'name',
        'phone',
        'customer_code',
        'email',
        'image',
        'password',
        'is_active',
        'is_completed',
        'points',
        'current_language',
        'referral_code',
        'referred_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'is_completed' => 'boolean',
        'points' => 'integer',
    ];

    /**
     * ERP CustomerCode: stored code when set, otherwise phone without country code.
     */
    public function resolveErpCustomerCode(): string
    {
        $code = trim((string) ($this->customer_code ?? ''));
        if ($code !== '') {
            return $code;
        }

        return KuwaitPhone::withoutCountryCode($this->phone);
    }

    /**
     * Get the profile image URL
     */
    public function getProfileImageUrlAttribute(): string
    {
        return $this->getFileUrl($this->image, 'public', 'no-image.png');
    }

    /**
     * Scope to get only active customers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only inactive customers
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Get the wallet for the customer
     */
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get the addresses for the customer
     */
    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Get the notifications for the customer.
     */
    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    /**
     * Get unread notifications for the customer.
     */
    public function unreadNotifications()
    {
        return $this->morphMany(Notification::class, 'notifiable')->where('is_read', false);
    }

    /**
     * Get the device tokens for the customer
     */
    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Get the customer who referred this customer
     */
    public function referrer()
    {
        return $this->belongsTo(Customer::class, 'referred_by');
    }

    /**
     * Get customers referred by this customer
     */
    public function referrals()
    {
        return $this->hasMany(Customer::class, 'referred_by');
    }

    /**
     * Generate a unique referral code for the customer
     */
    public static function generateUniqueReferralCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        } while (self::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Ensure customer has a referral code
     */
    public function ensureReferralCode(): string
    {
        if (!$this->referral_code) {
            $this->referral_code = self::generateUniqueReferralCode();
            $this->save();
        }

        return $this->referral_code;
    }

    /**
     * Get the number of successful referrals
     */
    public function getReferralCountAttribute(): int
    {
        return $this->referrals()->where('is_completed', true)->count();
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically generate referral code before customer is created
        static::creating(function ($customer) {
            if (!$customer->referral_code) {
                $customer->referral_code = self::generateUniqueReferralCode();
            }
        });

        // Automatically create wallet when customer is created
        static::created(function ($customer) {
            Wallet::create([
                'customer_id' => $customer->id,
                'balance' => 0.00,
            ]);
        });
    }
}
