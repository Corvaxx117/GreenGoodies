<?php

declare(strict_types=1);

namespace App\ApiResource\Order;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Porte les lignes de commande envoyées par le front au moment de la validation du panier session.
 */
final class CreateOrderInput
{
    /**
     * @var list<array{slug: string, quantity: int}>
     */
    #[Assert\NotNull]
    #[Assert\Count(min: 1, minMessage: 'Votre panier est vide.')]
    #[Assert\All([
        new Assert\Collection(
            fields: [
                'slug' => [
                    new Assert\NotBlank(),
                    new Assert\Type('string'),
                ],
                'quantity' => [
                    new Assert\NotNull(),
                    new Assert\Type('integer'),
                    new Assert\Positive(),
                ],
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ),
    ])]
    public array $items = [];
}
