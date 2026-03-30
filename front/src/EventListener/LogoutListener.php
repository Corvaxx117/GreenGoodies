<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\ApiLoginAuthenticator;
use App\Service\Cart\CartSessionManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Nettoie la session front en supprimant le JWT stocké lors de la déconnexion.
 */
#[AsEventListener(event: LogoutEvent::class)]
final class LogoutListener
{
    public function __invoke(LogoutEvent $event): void
    {
        // Le front conserve le JWT et désormais le panier en session ; ils doivent être retirés explicitement au logout.
        $event->getRequest()->getSession()->remove(ApiLoginAuthenticator::SESSION_JWT_KEY);
        $event->getRequest()->getSession()->remove(CartSessionManager::SESSION_KEY);
    }
}
