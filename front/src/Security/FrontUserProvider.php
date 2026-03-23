<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Fournisseur minimaliste utilisé uniquement pour accepter les FrontUser déjà créés par l'authenticator.
 */
final class FrontUserProvider implements UserProviderInterface
{
    public function refreshUser(UserInterface $user): UserInterface
    {
        // Le front ne recharge pas l'utilisateur depuis une base locale ; il valide simplement son type.
        if (!$user instanceof FrontUser) {
            throw new UnsupportedUserException(sprintf('Utilisateur non supporté : %s', $user::class));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, FrontUser::class, true);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Toute tentative de chargement direct doit passer par l'API, jamais par une source locale.
        throw new UserNotFoundException('Les utilisateurs front sont chargés via l’authenticator API.');
    }
}
