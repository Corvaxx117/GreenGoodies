<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\CartManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Valide le panier courant et le transforme en commande confirmée.
 */
final readonly class CheckoutCartController
{
    #[Route('/api/cart/checkout', name: 'api_cart_checkout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        #[CurrentUser] User $user,
        CartManager $cartManager,
    ): JsonResponse {
        try {
            // La validation déclenche la transition métier de la commande brouillon vers une commande validée.
            $order = $cartManager->checkout($user);
        } catch (\DomainException $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            // La référence et le total sont renvoyés pour alimenter le message de confirmation côté front.
            'message' => 'Commande validée avec succès.',
            'reference' => $order->getReference(),
            'validatedAt' => $order->getValidatedAt()?->format(DATE_ATOM),
            'totalCents' => $order->getTotalCents(),
        ]);
    }
}
