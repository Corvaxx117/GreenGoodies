<?php

declare(strict_types=1);

namespace App\ApiState\Order;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Retourne les commandes du compte courant.
 */
final readonly class CurrentUserOrdersProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private CustomerOrderRepository $customerOrderRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentification requise.');
        }

        return $this->customerOrderRepository->findByUser($user);
    }
}
