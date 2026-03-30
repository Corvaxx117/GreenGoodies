<?php

declare(strict_types=1);

namespace App\ApiState\Account;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Account\AccountView;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Agrège les données nécessaires à l'écran "Mon compte" du front.
 */
final readonly class AccountViewProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private CustomerOrderRepository $customerOrderRepository,
        private ProductRepository $productRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AccountView
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentification requise.');
        }

        // Le front récupère en un seul appel le profil synthétique, l'état API et les dernières commandes validées.
        return AccountView::fromUserOrdersAndProducts(
            $user,
            $this->customerOrderRepository->findLatestValidatedForUser($user, 10),
            $this->productRepository->findLatestBySeller($user, 8),
        );
    }
}
