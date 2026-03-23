<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\ApiLoginAuthenticator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class)]
final class LogoutListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->getRequest()->getSession()->remove(ApiLoginAuthenticator::SESSION_JWT_KEY);
    }
}
