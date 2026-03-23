<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Validated = 'validated';
    case Cancelled = 'cancelled';
}
