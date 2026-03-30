<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Nettoie l'état d'authentification local du front quand le JWT API n'est plus utilisable.
 */
final readonly class FrontAuthenticationManager
{
    private const FIREWALL_SESSION_KEY = '_security_main';

    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function clearAuthentication(Request $request): void
    {
        // Le token courant est supprimé pour que `app.user` redevienne anonyme immédiatement.
        $this->tokenStorage->setToken(null);

        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        // Le JWT applicatif et le token du firewall sont retirés pour éviter un rechargement ultérieur.
        $session->remove(ApiLoginAuthenticator::SESSION_JWT_KEY);
        $session->remove(self::FIREWALL_SESSION_KEY);
    }
}
