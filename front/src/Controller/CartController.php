<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ApiRequestException;
use App\Security\ApiLoginAuthenticator;
use App\Service\Api\GreenGoodiesApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Coordonne les écrans et actions du panier côté front.
 */
final class CartController extends AbstractController
{
    #[Route('/mon-panier', name: 'front_cart_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(Request $request, GreenGoodiesApiClient $apiClient): Response
    {
        $jwt = $this->getJwtFromSession($request);

        if ($jwt === null) {
            return $this->redirectToLogin();
        }

        try {
            // Le front lit l'état complet du panier depuis l'API pour éviter tout état local divergent.
            $cart = $apiClient->getCart($jwt);
        } catch (ApiRequestException $exception) {
            if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                return $this->redirectToLogin();
            }

            // Un échec technique garde l'écran accessible avec un panier vide de secours.
            $this->addFlash('error', $exception->getMessage());
            $cart = $this->emptyCart();
        }

        return $this->render('cart/show.html.twig', [
            'cart' => $cart,
        ]);
    }

    #[Route('/mon-panier/articles/{slug}', name: 'front_cart_item_upsert', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function upsertItem(string $slug, Request $request, GreenGoodiesApiClient $apiClient): Response
    {
        // Le formulaire produit est protégé par un jeton CSRF dédié au slug manipulé.
        if (!$this->isCsrfTokenValid(sprintf('front_cart_item_%s', $slug), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requête est invalide.');

            return $this->redirectToRoute('front_product_show', ['slug' => $slug]);
        }

        $jwt = $this->getJwtFromSession($request);

        if ($jwt === null) {
            return $this->redirectToLogin();
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
                return $this->redirectToLogin();
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('front_product_show', ['slug' => $slug]);
        }

        return $this->redirectToRoute('front_cart_show');
    }

    #[Route('/mon-panier/vider', name: 'front_cart_clear', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function clear(Request $request, GreenGoodiesApiClient $apiClient): Response
    {
        // La vidange du panier est aussi protégée par CSRF car elle modifie l'état métier.
        if (!$this->isCsrfTokenValid('front_cart_clear', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requête est invalide.');

            return $this->redirectToRoute('front_cart_show');
        }

        $jwt = $this->getJwtFromSession($request);

        if ($jwt === null) {
            return $this->redirectToLogin();
        }

        try {
            $response = $apiClient->clearCart($jwt);
            $this->addFlash('success', (string) ($response['message'] ?? 'Panier vidé.'));
        } catch (ApiRequestException $exception) {
            if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                return $this->redirectToLogin();
            }

            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('front_cart_show');
    }

    #[Route('/mon-panier/valider', name: 'front_cart_checkout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkout(Request $request, GreenGoodiesApiClient $apiClient): Response
    {
        // La validation de commande déclenche une transition métier irréversible draft -> validated.
        if (!$this->isCsrfTokenValid('front_cart_checkout', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requête est invalide.');

            return $this->redirectToRoute('front_cart_show');
        }

        $jwt = $this->getJwtFromSession($request);

        if ($jwt === null) {
            return $this->redirectToLogin();
        }

        try {
            $response = $apiClient->checkoutCart($jwt);
            $message = (string) ($response['message'] ?? 'Commande validée.');

            if (isset($response['reference']) && is_string($response['reference'])) {
                $message = sprintf('%s Référence : %s.', $message, $response['reference']);
            }

            $this->addFlash('success', $message);
        } catch (ApiRequestException $exception) {
            if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                return $this->redirectToLogin();
            }

            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('front_cart_show');
    }

    /**
     * Tente de récupérer le JWT en session, ou null si la session est invalide ou expirée.
     */
    private function getJwtFromSession(Request $request): ?string
    {
        $jwt = (string) $request->getSession()->get(ApiLoginAuthenticator::SESSION_JWT_KEY, '');

        return $jwt !== '' ? $jwt : null;
    }

    private function redirectToLogin(): Response
    {
        // Une session front sans JWT valide est traitée comme une expiration d'authentification API.
        $this->addFlash('error', 'Votre session API a expiré. Merci de vous reconnecter.');

        return $this->redirectToRoute('front_login');
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCart(): array
    {
        // Ce fallback permet de rendre l'écran même quand l'API ne renvoie pas le panier.
        return [
            'reference' => null,
            'status' => 'draft',
            'itemCount' => 0,
            'deliveryCents' => 0,
            'deliveryLabel' => 'Offert',
            'totalCents' => 0,
            'isEmpty' => true,
            'items' => [],
        ];
    }
}
