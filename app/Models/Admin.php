<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasPermissions;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasPermissions;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    /**
     * Get the role that belongs to this admin.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the notifications for the admin.
     */
    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    /**
     * Get unread notifications for the admin.
     */
    public function unreadNotifications()
    {
        return $this->morphMany(Notification::class, 'notifiable')->where('is_read', false);
    }

}
