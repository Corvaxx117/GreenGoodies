<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\CartManager;
use App\Service\CartPayloadFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Expose l'état courant du panier de l'utilisateur authentifié.
 */
final readonly class CartController
{
    #[Route('/api/cart', name: 'api_cart', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        #[CurrentUser] User $user,
        CartManager $cartManager,
        CartPayloadFactory $cartPayloadFactory,
    ): JsonResponse {
        // Le payload renvoyé est déjà mis en forme pour être rendu directement par le front.
        return new JsonResponse($cartPayloadFactory->create($cartManager->getDraftOrder($user)));
    }
}
