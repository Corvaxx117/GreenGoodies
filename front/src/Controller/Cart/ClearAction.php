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
 * Vide le panier de l'utilisateur connecté.
 */
final class ClearAction extends AbstractController
{
    #[Route('/mon-panier/vider', name: 'front_cart_clear', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request, CartSessionManager $cartSessionManager): Response
    {
        // La vidange du panier est aussi protégée par CSRF car elle modifie l'état métier.
        if (!$this->isCsrfTokenValid('front_cart_clear', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requête est invalide.');

            return $this->redirectToRoute('front_cart_show');
        }

        $cartSessionManager->clear($request->getSession());
        $this->addFlash('success', 'Panier vidé.');

        return $this->redirectToRoute('front_cart_show');
    }
}
