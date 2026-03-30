<?php

declare(strict_types=1);

namespace App\ApiResource\Order;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\ApiState\Order\CreateOrderProcessor;
use App\Entity\CustomerOrder;

/**
 * Transporte le résultat de la création d'une commande validée.
 */
#[ApiResource(
    shortName: 'Order',
    operations: [
        new Post(
            uriTemplate: '/orders',
            input: CreateOrderInput::class,
            output: self::class,
            read: false,
            processor: CreateOrderProcessor::class,
            security: "is_granted('ROLE_USER')",
            openapi: new OpenApiOperation(
                tags: ['Orders'],
                summary: 'Créer une commande',
                description: 'Transforme le panier session du front en commande validée côté API.',
                security: [['JWT' => []]],
            ),
        ),
    ],
)]
final class OrderResult
{
    public string $message = 'Commande validée avec succès.';

    public string $reference = '';

    public ?string $validatedAt = null;

    public int $totalCents = 0;

    /**
     * Déduit le payload de confirmation à partir de la commande créée.
     */
    public static function fromOrder(CustomerOrder $order): self
    {
        $view = new self();
        $view->reference = $order->getReference();
        $view->validatedAt = $order->getValidatedAt()?->format(DATE_ATOM);
        $view->totalCents = $order->getTotalCents();

        return $view;
    }
}
