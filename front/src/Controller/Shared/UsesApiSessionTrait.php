<?php

declare(strict_types=1);

namespace App\Controller\Shared;

use App\Security\ApiLoginAuthenticator;
use App\Security\FrontAuthenticationManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Factorise la lecture du JWT stocké en session front et la redirection vers la reconnexion.
 */
trait UsesApiSessionTrait
{
    protected function getJwtFromSession(Request $request): ?string
    {
        $jwt = (string) $request->getSession()->get(ApiLoginAuthenticator::SESSION_JWT_KEY, '');

        return $jwt !== '' ? $jwt : null;
    }

    protected function clearLocalAuthentication(Request $request, FrontAuthenticationManager $frontAuthenticationManager): void
    {
        $frontAuthenticationManager->clearAuthentication($request);
    }

    protected function redirectToLogin(Request $request, FrontAuthenticationManager $frontAuthenticationManager): Response
    {
        // Le front purge son état local avant de rediriger l'utilisateur vers la reconnexion.
        $this->clearLocalAuthentication($request, $frontAuthenticationManager);
        $this->addFlash('error', 'Votre session a expiré. Merci de vous reconnecter.');

        return $this->redirectToRoute('front_login');
    }
}
