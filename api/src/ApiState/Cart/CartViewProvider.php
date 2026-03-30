<?php

declare(strict_types=1);

namespace App\ApiState\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Cart\CartView;
use App\Entity\User;
use App\Service\CartManager;
use App\Service\CartPayloadFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Retourne l'état courant du panier de l'utilisateur authentifié sous forme de vue dédiée.
 */
final readonly class CartViewProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private CartManager $cartManager,
        private CartPayloadFactory $cartPayloadFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CartView
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentification requise.');
        }

        return CartView::fromPayload(
            $this->cartPayloadFactory->create($this->cartManager->getDraftOrder($user)),
        );
    }
}
