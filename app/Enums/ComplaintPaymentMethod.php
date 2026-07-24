<?php

namespace App\Enums;

enum ComplaintPaymentMethod: string
{
    case Cash = 'cash';
    case Wallet = 'wallet';
    case Card = 'card';
    case OnlineLink = 'online_link';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
