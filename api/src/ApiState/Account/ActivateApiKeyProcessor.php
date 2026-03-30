<?php

declare(strict_types=1);

namespace App\ApiState\Account;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Account\ActivateApiKeyResult;
use App\Entity\ApiKey;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Active l'accès API commerçant et régénère la clé associée au compte courant.
 */
final readonly class ActivateApiKeyProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ActivateApiKeyResult
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentification requise.');
        }

        // La clé n'est produite en clair qu'au moment de cette activation.
        $plainKey = sprintf('GGK_%s', strtoupper(bin2hex(random_bytes(20))));

        $user->enableApiAccess();

        if ($user->getApiKey() instanceof ApiKey) {
            $user->getApiKey()->rotate($plainKey, true);
        } else {
            $this->entityManager->persist(new ApiKey($user, $plainKey, true));
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new ActivateApiKeyResult('Accès API activé.', $plainKey);
    }
}
