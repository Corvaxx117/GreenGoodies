<?php

declare(strict_types=1);

namespace App\Controller\Cart;

use App\Exception\ApiRequestException;
use App\HttpClient\GreenGoodies\ProductClient;
use App\Service\Cart\CartSessionManager;
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
    public function __construct(
        private readonly ProductClient $productClient,
        private readonly CartSessionManager $cartSessionManager,
    ) {
    }

    #[Route('/mon-panier', name: 'front_cart_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): Response
    {
        try {
            // Le panier vit en session, mais les détails produits viennent toujours du catalogue exposé par l'API.
            $cart = $this->cartSessionManager->buildView($request->getSession(), $this->productClient->listProducts());
        } catch (ApiRequestException $exception) {
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
            'itemCount' => 0,
            'deliveryCents' => 0,
            'deliveryLabel' => 'Offert',
            'totalCents' => 0,
            'isEmpty' => true,
            'items' => [],
        ];
    }
}
