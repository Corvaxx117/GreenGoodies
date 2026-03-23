<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\ProductRepository;
use App\Service\CartManager;
use App\Service\CartPayloadFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Ajoute, met à jour ou retire une ligne du panier courant.
 */
final readonly class CartItemController
{
    #[Route('/api/cart/items/{slug}', name: 'api_cart_item_upsert', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        string $slug,
        Request $request,
        #[CurrentUser] User $user,
        ProductRepository $productRepository,
        CartManager $cartManager,
        CartPayloadFactory $cartPayloadFactory,
    ): JsonResponse {
        // Le slug identifie le produit public qui peut être manipulé dans le panier.
        $product = $productRepository->findOnePublishedBySlug($slug);

        if ($product === null) {
            return new JsonResponse([
                'message' => 'Produit introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            // Un JSON invalide est ramené à un payload vide pour déclencher la validation de quantité.
            $payload = [];
        }

        $quantity = filter_var($payload['quantity'] ?? null, FILTER_VALIDATE_INT);

        if ($quantity === false || $quantity < 0) {
            return new JsonResponse([
                'message' => 'La quantité doit être un entier positif ou nul.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Toute la logique panier est ensuite déléguée au service métier dédié.
        $order = $cartManager->updateItem($user, $product, $quantity);

        return new JsonResponse($cartPayloadFactory->create($order));
    }
}
