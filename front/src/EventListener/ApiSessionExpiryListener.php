<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\ApiLoginAuthenticator;
use App\Security\FrontAuthenticationManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Synchronise le front avec l'expiration du JWT conservé en session.
 */
#[AsEventListener(event: RequestEvent::class)]
final readonly class ApiSessionExpiryListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private FrontAuthenticationManager $frontAuthenticationManager,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($this->tokenStorage->getToken() === null || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $jwt = (string) $session->get(ApiLoginAuthenticator::SESSION_JWT_KEY, '');

        if ($jwt === '') {
            $this->frontAuthenticationManager->clearAuthentication($request);

            return;
        }

        if (!$this->isJwtExpired($jwt)) {
            return;
        }

        // Request::getSession() retourne l'interface générique de session
        if ($session instanceof FlashBagAwareSessionInterface) {
            // Le message est posé avant le nettoyage pour être visible sur la prochaine page rendue.
            $session->getFlashBag()->add('error', 'Votre session API a expiré. Merci de vous reconnecter.');
        }

        $this->frontAuthenticationManager->clearAuthentication($request);
    }

    private function isJwtExpired(string $jwt): bool
    {
        $payload = $this->decodePayload($jwt);

        if (!is_array($payload) || !isset($payload['exp']) || !is_numeric($payload['exp'])) {
            return false;
        }

        return (int) $payload['exp'] <= time();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePayload(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = base64_decode($this->normalizeBase64($parts[1]), true);

        if ($payload === false) {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeBase64(string $value): string
    {
        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if ($padding === 0) {
            return $normalized;
        }

        return str_pad($normalized, strlen($normalized) + (4 - $padding), '=', STR_PAD_RIGHT);
    }
}
