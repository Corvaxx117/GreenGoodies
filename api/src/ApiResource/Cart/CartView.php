<?php

declare(strict_types=1);

namespace App\ApiResource\Cart;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\ApiResource\Shared\MessageResource;
use App\ApiState\Cart\CartViewProvider;
use App\ApiState\Cart\CheckoutCartProcessor;
use App\ApiState\Cart\ClearCartProcessor;
use App\ApiState\Cart\UpsertCartItemProcessor;

/**
 * Expose la lecture et les commandes métier du panier via API Platform.
 */
#[ApiResource(
    shortName: 'Cart',
    operations: [
        new Get(
            uriTemplate: '/cart',
            output: self::class,
            provider: CartViewProvider::class,
            security: "is_granted('ROLE_USER')",
            openapi: new OpenApiOperation(
                tags: ['Cart'],
                summary: 'Récupérer le panier courant',
                description: 'Retourne la commande brouillon de l’utilisateur connecté.',
                security: [['JWT' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/cart/items/{slug}',
            input: CartItemInput::class,
            output: self::class,
            read: false,
            processor: UpsertCartItemProcessor::class,
            security: "is_granted('ROLE_USER')",
            openapi: new OpenApiOperation(
                tags: ['Cart'],
                summary: 'Ajouter ou mettre à jour un produit du panier',
                description: 'Met à jour la quantité d’un produit dans le panier courant. `0` retire le produit.',
                security: [['JWT' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/cart/clear',
            input: false,
            output: MessageResource::class,
            read: false,
            processor: ClearCartProcessor::class,
            security: "is_granted('ROLE_USER')",
            openapi: new OpenApiOperation(
                tags: ['Cart'],
                summary: 'Vider le panier',
                description: 'Supprime la commande brouillon en cours.',
                security: [['JWT' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/cart/checkout',
            input: false,
            output: CheckoutResult::class,
            read: false,
            processor: CheckoutCartProcessor::class,
            security: "is_granted('ROLE_USER')",
            openapi: new OpenApiOperation(
                tags: ['Cart'],
                summary: 'Valider la commande',
                description: 'Valide le panier courant et le transforme en commande.',
                security: [['JWT' => []]],
            ),
        ),
    ],
)]
final class CartView
{
    public ?string $reference = null;

    public string $status = 'draft';

    public int $itemCount = 0;

    public int $deliveryCents = 0;

    public string $deliveryLabel = 'Offert';

    public int $totalCents = 0;

    public bool $isEmpty = true;

    /**
     * @var list<CartItemView>
     */
    public array $items = [];

    /**
     * Transforme le payload métier du panier en vue stable pour le front.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        $view = new self();
        $view->reference = isset($payload['reference']) && is_string($payload['reference']) ? $payload['reference'] : null;
        $view->status = (string) ($payload['status'] ?? 'draft');
        $view->itemCount = (int) ($payload['itemCount'] ?? 0);
        $view->deliveryCents = (int) ($payload['deliveryCents'] ?? 0);
        $view->deliveryLabel = (string) ($payload['deliveryLabel'] ?? 'Offert');
        $view->totalCents = (int) ($payload['totalCents'] ?? 0);
        $view->isEmpty = (bool) ($payload['isEmpty'] ?? true);
        $view->items = array_map(
            static fn (array $item): CartItemView => CartItemView::fromPayload($item),
            array_values(array_filter(
                is_array($payload['items'] ?? null) ? $payload['items'] : [],
                static fn (mixed $item): bool => is_array($item),
            )),
        );

        return $view;
    }
}
