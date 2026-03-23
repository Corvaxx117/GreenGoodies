<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Représente dans le front l'utilisateur authentifié par l'API.
 */
final class FrontUser implements UserInterface, EquatableInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private readonly int $id,
        private readonly string $email,
        private readonly string $firstName,
        private readonly string $lastName,
        private readonly array $roles,
    ) {}

    /**
     * Construit un utilisateur front à partir du payload minimal exposé par l'API, ou lance une exception si les données sont manquantes ou invalides.
     * @throws \InvalidArgumentException
     * @param array<string, mixed> $payload
     */
    public static function fromApiPayload(array $payload): self
    {
        // Le front reconstruit son utilisateur à partir du payload minimal exposé par /api/me.
        return new self(
            (int) $payload['id'],
            (string) $payload['email'],
            (string) $payload['firstName'],
            (string) $payload['lastName'],
            array_values(array_unique(array_map('strval', $payload['roles'] ?? ['ROLE_USER']))),
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function eraseCredentials(): void {}

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return trim(sprintf('%s %s', $this->firstName, $this->lastName));
    }

    public function isEqualTo(UserInterface $user): bool
    {
        // Cette comparaison évite de recharger un utilisateur local qui n'existe pas en base front.
        return $user instanceof self
            && $this->id === $user->id
            && $this->email === $user->email
            && $this->getRoles() === $user->getRoles();
    }
}
