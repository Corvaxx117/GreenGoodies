<?php

declare(strict_types=1);

namespace App\ApiState\Account;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Shared\MessageResource;
use App\Entity\Merchant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Désactive l'accès API commerçant du compte courant et la clé associée.
 */
final readonly class DeactivateApiKeyProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MessageResource
    {
        $user = $this->security->getUser();

        if (!$user instanceof Merchant) {
            throw new AccessDeniedException('Réservé aux commerçants.');
        }

        // Le flag utilisateur et la clé doivent être désactivés de concert pour fermer complètement l'accès.
        $user->disableApiAccess();

        if ($user->getApiKey() !== null) {
            $user->getApiKey()->disable();
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new MessageResource('Accès API désactivé.');
    }
}
