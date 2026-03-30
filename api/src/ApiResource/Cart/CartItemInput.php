<?php

declare(strict_types=1);

namespace App\ApiResource\Cart;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Porte la quantité à appliquer à une ligne de panier lors d'un ajout ou d'une mise à jour.
 */
final class CartItemInput
{
    #[Assert\NotNull]
    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero]
    public ?int $quantity = null;
}
