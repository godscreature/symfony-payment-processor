<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case NEW = 'NEW';
    case PAID = 'PAID';
    case CANCELLED = 'CANCELLED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
