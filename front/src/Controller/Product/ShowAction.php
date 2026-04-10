<?php

declare(strict_types=1);

namespace App\Controller\Product;

use App\Exception\ApiRequestException;
use App\HttpClient\GreenGoodies\ProductClient;
use App\Service\Cart\CartSessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Affiche la fiche produit et préremplit la quantité déjà présente dans le panier session.
 */
final class ShowAction extends AbstractController
{
    public function __construct(
        private readonly ProductClient $productClient,
        private readonly CartSessionManager $cartSessionManager,
    ) {
    }

    #[Route('/produits/{slug}', name: 'front_product_show', methods: ['GET'])]
    public function __invoke(string $slug, Request $request): Response
    {
        try {
            // Le détail produit vient du catalogue exposé par l'API.
            $product = $this->productClient->getProduct($slug);
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
            $currentQuantity = $this->cartSessionManager->getQuantity($request->getSession(), $slug);
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'currentQuantity' => $currentQuantity,
        ]);
    }
}
