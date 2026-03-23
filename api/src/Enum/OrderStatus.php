<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Décrit les états métier possibles d'une commande.
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case Validated = 'validated';
    case Cancelled = 'cancelled';
}
