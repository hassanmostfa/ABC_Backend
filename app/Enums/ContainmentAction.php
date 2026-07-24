<?php

namespace App\Enums;

enum ContainmentAction: string
{
    case Hold = 'hold';
    case Recall = 'recall';
    case RegulatoryNotification = 'regulatory_notification';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
