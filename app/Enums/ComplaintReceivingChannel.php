<?php

namespace App\Enums;

enum ComplaintReceivingChannel: string
{
    case Phone = 'phone';
    case Email = 'email';
    case WalkIn = 'walk_in';
    case SocialMedia = 'social_media';
    case Website = 'website';
    case MobileApp = 'mobile_app';
    case CallCenter = 'call_center';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
