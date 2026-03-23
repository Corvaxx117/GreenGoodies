<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\ApiLoginAuthenticator;
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
        // Le front conserve le JWT en session ; il doit donc être retiré explicitement au logout.
        $event->getRequest()->getSession()->remove(ApiLoginAuthenticator::SESSION_JWT_KEY);
    }
}
