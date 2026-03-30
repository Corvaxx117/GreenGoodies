<?php

declare(strict_types=1);

namespace App\Controller\Cart;

use App\Service\Cart\CartSessionManager;
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
    #[Route('/mon-panier/articles/{slug}', name: 'front_cart_item_upsert', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        string $slug,
        Request $request,
        CartSessionManager $cartSessionManager,
    ): Response
    {
        // Le formulaire produit est protégé par un jeton CSRF dédié au slug manipulé.
        if (!$this->isCsrfTokenValid(sprintf('front_cart_item_%s', $slug), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requête est invalide.');

            return $this->redirectToRoute('front_product_show', ['slug' => $slug]);
        }

        $quantity = filter_var($request->request->get('quantity'), FILTER_VALIDATE_INT);

        if ($quantity === false || $quantity < 0) {
            $this->addFlash('error', 'La quantité doit être un entier positif ou nul.');

            return $this->redirectToRoute('front_product_show', ['slug' => $slug]);
        }

        // Le panier front n'appelle plus l'API : la quantité est conservée en session.
        $cartSessionManager->upsert($request->getSession(), $slug, $quantity);
        $this->addFlash(
            'success',
            $quantity === 0 ? 'Produit retiré du panier.' : 'Panier mis à jour.',
        );

        return $this->redirectToRoute('front_cart_show');
    }
}
