<?php

namespace App\Enums;

enum FoodSafetyIndicator: string
{
    case OffOdor = 'off_odor';
    case SwollenPack = 'swollen_pack';
    case ForeignBody = 'foreign_body';
    case Illness = 'illness';
    case Discoloration = 'discoloration';
    case UnusualTaste = 'unusual_taste';
    case Leakage = 'leakage';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
