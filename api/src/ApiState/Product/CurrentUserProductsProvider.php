<?php

declare(strict_types=1);

namespace App\ApiState\Product;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Merchant;
use App\Repository\ProductRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Retourne les produits du commerçant authentifié côté front.
 */
final readonly class CurrentUserProductsProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository $productRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $user = $this->security->getUser();

        if (!$user instanceof Merchant) {
            throw new AccessDeniedException('Réservé aux commerçants.');
        }

        return $this->productRepository->findBySeller($user);
    }
}
