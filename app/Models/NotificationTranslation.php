<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTranslation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'notification_id',
        'locale',
        'title',
        'message',
    ];

    /**
     * Get the parent notification.
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
