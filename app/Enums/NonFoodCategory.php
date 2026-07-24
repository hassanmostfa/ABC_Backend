<?php

namespace App\Enums;

enum NonFoodCategory: string
{
    case Packaging = 'packaging';
    case Labelling = 'labelling';
    case Delivery = 'delivery';
    case Service = 'service';
    case Marketing = 'marketing';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
