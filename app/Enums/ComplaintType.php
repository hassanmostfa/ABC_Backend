<?php

namespace App\Enums;

enum ComplaintType: string
{
    case FoodSafety = 'food_safety';
    case NonFoodSafety = 'non_food_safety';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
