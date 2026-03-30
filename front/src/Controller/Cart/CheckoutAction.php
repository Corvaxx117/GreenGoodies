<?php

declare(strict_types=1);

namespace App\Controller\Cart;

use App\Controller\Shared\UsesApiSessionTrait;
use App\Exception\ApiRequestException;
use App\Security\FrontAuthenticationManager;
use App\Service\Api\GreenGoodiesApiClient;
use App\Service\Cart\CartSessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Valide le panier courant et déclenche la création d'une commande confirmée.
 */
final class CheckoutAction extends AbstractController
{
    use UsesApiSessionTrait;

    #[Route('/mon-panier/valider', name: 'front_cart_checkout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        Request $request,
        GreenGoodiesApiClient $apiClient,
        FrontAuthenticationManager $frontAuthenticationManager,
        CartSessionManager $cartSessionManager,
    ): Response
    {
        // La validation de commande envoie le panier session à l'API pour créer une commande réelle.
        if (!$this->isCsrfTokenValid('front_cart_checkout', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requête est invalide.');

            return $this->redirectToRoute('front_cart_show');
        }

        $jwt = $this->getJwtFromSession($request);

        if ($jwt === null) {
            return $this->redirectToLogin($request, $frontAuthenticationManager);
        }

        if (!$cartSessionManager->hasItems($request->getSession())) {
            $this->addFlash('error', 'Votre panier est vide.');

            return $this->redirectToRoute('front_cart_show');
        }

        try {
            $response = $apiClient->createOrder(
                ['items' => $cartSessionManager->toOrderPayload($request->getSession())],
                $jwt,
            );
            $message = (string) ($response['message'] ?? 'Commande validée.');

            if (isset($response['reference']) && is_string($response['reference'])) {
                $message = sprintf('%s Référence : %s.', $message, $response['reference']);
            }

            $cartSessionManager->clear($request->getSession());
            $this->addFlash('success', $message);
        } catch (ApiRequestException $exception) {
            if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                return $this->redirectToLogin($request, $frontAuthenticationManager);
            }

            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('front_cart_show');
    }
}
