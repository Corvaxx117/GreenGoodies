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
 * Affiche l'état du panier courant de l'utilisateur connecté.
 */
final class ShowAction extends AbstractController
{
    use UsesApiSessionTrait;

    #[Route('/mon-panier', name: 'front_cart_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        Request $request,
        GreenGoodiesApiClient $apiClient,
        FrontAuthenticationManager $frontAuthenticationManager,
    ): Response
    {
        $jwt = $this->getJwtFromSession($request);

        if ($jwt === null) {
            return $this->redirectToLogin($request, $frontAuthenticationManager);
        }

        try {
            // Le front lit l'état complet du panier depuis l'API pour éviter tout état local divergent.
            $cart = $apiClient->getCart($jwt);
        } catch (ApiRequestException $exception) {
            if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                return $this->redirectToLogin($request, $frontAuthenticationManager);
            }

            // Un échec technique garde l'écran accessible avec un panier vide de secours.
            $this->addFlash('error', $exception->getMessage());
            $cart = $this->emptyCart();
        }

        return $this->render('cart/show.html.twig', [
            'cart' => $cart,
        ]);
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
