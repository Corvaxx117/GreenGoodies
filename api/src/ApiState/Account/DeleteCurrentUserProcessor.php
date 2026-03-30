<?php

declare(strict_types=1);

namespace App\ApiState\Account;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Shared\MessageResource;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Supprime le compte courant sans passer par une route Symfony controller dédiée.
 */
final readonly class DeleteCurrentUserProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MessageResource
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentification requise.');
        }

        // Les cascades Doctrine gèrent ensuite la suppression des données dépendantes.
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new MessageResource('Compte supprimé.');
    }
}
