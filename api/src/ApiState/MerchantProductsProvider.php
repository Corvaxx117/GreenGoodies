<?php

declare(strict_types=1);

namespace App\ApiState;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\ProductRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class MerchantProductsProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository $productRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Clé API invalide.');
        }

        return $this->productRepository->findPublishedBySeller($user);
    }
}
