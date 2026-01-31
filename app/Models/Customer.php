<?php

namespace App\Models;

use App\Traits\ManagesFileUploads;
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
        'email',
        'image',
        'password',
        'is_active',
        'is_completed',
        'points',
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
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically create wallet when customer is created
        static::created(function ($customer) {
            Wallet::create([
                'customer_id' => $customer->id,
                'balance' => 0.00,
            ]);
        });
    }
}
