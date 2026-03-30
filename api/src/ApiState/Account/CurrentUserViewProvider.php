<?php

declare(strict_types=1);

namespace App\ApiState\Account;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Account\CurrentUserView;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Résout l'utilisateur courant et le projette dans la vue dédiée au front.
 */
final readonly class CurrentUserViewProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CurrentUserView
    {
        // Le profil courant ne peut être construit qu'à partir d'un utilisateur authentifié par JWT.
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentification requise.');
        }

        return CurrentUserView::fromUser($user);
    }
}
