<?php

declare(strict_types=1);

namespace App\Controller\Product;

use App\Exception\ApiRequestException;
use App\Security\ApiLoginAuthenticator;
use App\Security\FrontAuthenticationManager;
use App\Service\Api\GreenGoodiesApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Affiche la fiche produit et synchronise l'état du panier courant pour l'utilisateur connecté.
 */
final class ShowAction extends AbstractController
{
    #[Route('/produits/{slug}', name: 'front_product_show', methods: ['GET'])]
    public function __invoke(
        string $slug,
        Request $request,
        GreenGoodiesApiClient $apiClient,
        FrontAuthenticationManager $frontAuthenticationManager,
    ): Response
    {
        try {
            // Le détail produit vient du catalogue exposé par l'API.
            $product = $apiClient->getProduct($slug);
        } catch (ApiRequestException $exception) {
            if ($exception->getStatusCode() === Response::HTTP_NOT_FOUND) {
                throw $this->createNotFoundException('Produit introuvable.');
            }

            // Les autres erreurs techniques renvoient vers l'accueil avec un message utilisateur.
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('front_home');
        }

        $currentQuantity = 0;

        if ($this->getUser() !== null) {
            $jwt = (string) $request->getSession()->get(ApiLoginAuthenticator::SESSION_JWT_KEY, '');

            if ($jwt !== '') {
                try {
                    // Cette lecture permet de préremplir la quantité déjà présente dans le panier.
                    $cart = $apiClient->getCart($jwt);
                    $currentQuantity = $this->extractCurrentQuantity($cart, $slug);
                } catch (ApiRequestException $exception) {
                    $currentQuantity = 0;

                    if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                        $frontAuthenticationManager->clearAuthentication($request);
                    } else {
                        $this->addFlash('error', 'Impossible de récupérer votre panier pour le moment.');
                    }
                }
            }
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'currentQuantity' => $currentQuantity,
        ]);
    }

    /**
     * Extrait la quantité courante d'un produit dans le panier, ou 0 si le produit n'y est pas.
     * @param array<string, mixed> $cart
     */
    private function extractCurrentQuantity(array $cart, string $slug): int
    {
        $items = $cart['items'] ?? [];

        if (!is_array($items)) {
            return 0;
        }

        foreach ($items as $item) {
            if (!is_array($item) || ($item['productSlug'] ?? null) !== $slug) {
                continue;
            }

            return max(0, (int) ($item['quantity'] ?? 0));
        }

        return 0;
    }
}
