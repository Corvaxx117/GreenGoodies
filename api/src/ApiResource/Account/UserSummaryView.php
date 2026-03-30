<?php

declare(strict_types=1);

namespace App\ApiResource\Account;

use App\Entity\User;

/**
 * Résume un utilisateur dans les vues agrégées qui n'ont pas besoin de l'ensemble de son profil.
 */
final class UserSummaryView
{
    public int $id;

    public string $email;

    public string $firstName;

    public string $lastName;

    /**
     * Réduit l'entité User à ses informations d'identité utiles au front.
     */
    public static function fromUser(User $user): self
    {
        $view = new self();
        $view->id = $user->getId() ?? 0;
        $view->email = $user->getEmail();
        $view->firstName = $user->getFirstName();
        $view->lastName = $user->getLastName();

        return $view;
    }
}
