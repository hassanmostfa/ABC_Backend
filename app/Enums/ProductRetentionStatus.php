<?php

namespace App\Enums;

enum ProductRetentionStatus: string
{
    case Secured = 'secured';
    case Disposed = 'disposed';
    case Partial = 'partial';
    case Unknown = 'unknown';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
