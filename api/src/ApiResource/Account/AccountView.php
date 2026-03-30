<?php

declare(strict_types=1);

namespace App\ApiResource\Account;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\ApiState\Account\AccountViewProvider;
use App\Entity\CustomerOrder;
use App\Entity\Product;
use App\Entity\User;

/**
 * Agrège les données de profil, commandes et accès API affichées sur la page "Mon compte".
 */
#[ApiResource(
    shortName: 'Account',
    operations: [
        new Get(
            uriTemplate: '/account',
            output: self::class,
            provider: AccountViewProvider::class,
            security: "is_granted('ROLE_USER')",
            openapi: new OpenApiOperation(
                tags: ['Users'],
                summary: 'Récupérer les données du compte',
                description: 'Retourne les dernières commandes validées et l’état de l’accès API du compte.',
                security: [['JWT' => []]],
            ),
        ),
    ],
)]
final class AccountView
{
    public UserSummaryView $user;

    public bool $apiAccessEnabled = false;

    public ?string $apiKeyPrefix = null;

    /**
     * @var list<AccountOrderView>
     */
    public array $orders = [];

    /**
     * @var list<AccountProductView>
     */
    public array $products = [];

    /**
     * Construit la vue agrégée du compte à partir de l'utilisateur courant, de ses commandes et de ses produits.
     *
     * @param list<CustomerOrder> $orders
     * @param list<Product>       $products
     */
    public static function fromUserOrdersAndProducts(User $user, array $orders, array $products): self
    {
        $view = new self();
        $view->user = UserSummaryView::fromUser($user);
        $view->apiAccessEnabled = $user->isApiAccessEnabled();
        $view->apiKeyPrefix = $user->getApiKey()?->getKeyPrefix();
        $view->orders = array_map(
            static fn (CustomerOrder $order): AccountOrderView => AccountOrderView::fromOrder($order),
            $orders,
        );
        $view->products = array_map(
            static fn (Product $product): AccountProductView => AccountProductView::fromProduct($product),
            $products,
        );

        return $view;
    }
}
