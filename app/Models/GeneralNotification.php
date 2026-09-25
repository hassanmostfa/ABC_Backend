<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A notification broadcast by an admin to all active customers.
 * Each recipient gets their own row in `notifications` linked back via general_notification_id.
 */
class GeneralNotification extends Model
{
    use HasFactory;

    public const TYPE_GENERAL = 'general';
    public const TYPE_OFFER = 'offer';

    public const TYPES = [
        self::TYPE_GENERAL,
        self::TYPE_OFFER,
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'admin_id',
        'type',
        'offer_id',
        'title_en',
        'title_ar',
        'message_en',
        'message_ar',
        'status',
        'recipients_count',
        'push_sent_count',
        'push_failed_count',
        'total_chunks',
        'processed_chunks',
        'started_at',
        'completed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'recipients_count' => 'integer',
        'push_sent_count' => 'integer',
        'push_failed_count' => 'integer',
        'total_chunks' => 'integer',
        'processed_chunks' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function isOffer(): bool
    {
        return $this->type === self::TYPE_OFFER;
    }

    public function titleFor(string $locale): string
    {
        return $locale === 'ar' ? $this->title_ar : $this->title_en;
    }

    public function messageFor(string $locale): string
    {
        return $locale === 'ar' ? $this->message_ar : $this->message_en;
    }

    /**
     * Payload stored on each customer notification and sent as FCM data.
     * The mobile app routes to offer details when type is "offer".
     *
     * @return array<string, int>
     */
    public function payload(): array
    {
        $payload = ['general_notification_id' => $this->id];

        if ($this->isOffer() && $this->offer_id) {
            $payload['offer_id'] = $this->offer_id;
        }

        return $payload;
    }
}
