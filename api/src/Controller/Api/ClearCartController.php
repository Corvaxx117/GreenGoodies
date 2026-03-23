<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\CartManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Supprime la commande brouillon utilisée comme panier.
 */
final readonly class ClearCartController
{
    #[Route('/api/cart/clear', name: 'api_cart_clear', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        #[CurrentUser] User $user,
        CartManager $cartManager,
    ): JsonResponse {
        // Vider le panier revient à supprimer complètement le draft courant.
        $cartManager->clear($user);

        return new JsonResponse([
            'message' => 'Panier vidé.',
        ]);
    }
}
