<?php

declare(strict_types=1);

namespace App\Controller\Cart;

use App\Controller\Shared\UsesApiSessionTrait;
use App\Exception\ApiRequestException;
use App\Security\FrontAuthenticationManager;
use App\Service\Api\GreenGoodiesApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Ajoute, met à jour ou retire un produit du panier courant.
 */
final class UpsertItemAction extends AbstractController
{
    use UsesApiSessionTrait;

    #[Route('/mon-panier/articles/{slug}', name: 'front_cart_item_upsert', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        string $slug,
        Request $request,
        GreenGoodiesApiClient $apiClient,
        FrontAuthenticationManager $frontAuthenticationManager,
    ): Response
    {
        // Le formulaire produit est protégé par un jeton CSRF dédié au slug manipulé.
        if (!$this->isCsrfTokenValid(sprintf('front_cart_item_%s', $slug), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requête est invalide.');

            return $this->redirectToRoute('front_product_show', ['slug' => $slug]);
        }

        $jwt = $this->getJwtFromSession($request);

        if ($jwt === null) {
            return $this->redirectToLogin($request, $frontAuthenticationManager);
        }

        $quantity = filter_var($request->request->get('quantity'), FILTER_VALIDATE_INT);

        if ($quantity === false || $quantity < 0) {
            $this->addFlash('error', 'La quantité doit être un entier positif ou nul.');

            return $this->redirectToRoute('front_product_show', ['slug' => $slug]);
        }

        try {
            // Une quantité à 0 est interprétée par l'API comme une suppression de ligne.
            $apiClient->updateCartItem($slug, $quantity, $jwt);
            $this->addFlash(
                'success',
                $quantity === 0 ? 'Produit retiré du panier.' : 'Panier mis à jour.',
            );
        } catch (ApiRequestException $exception) {
            if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                return $this->redirectToLogin($request, $frontAuthenticationManager);
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('front_product_show', ['slug' => $slug]);
        }

        return $this->redirectToRoute('front_cart_show');
    }
}
