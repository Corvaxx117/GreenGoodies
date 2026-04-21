<?php

declare(strict_types=1);

namespace App\ApiState\Product;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Merchant;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Expose un produit publié au public, ou un produit privé à son propriétaire authentifié.
 */
final readonly class ProductItemProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository $productRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Product
    {
        $slug = trim((string) ($uriVariables['slug'] ?? ''));

        if ($slug === '') {
            return null;
        }

        $product = $this->productRepository->findOneBySlug($slug);

        if (!$product instanceof Product) {
            return null;
        }

        if ($product->isPublished()) {
            return $product;
        }

        $user = $this->security->getUser();

        if ($user instanceof Merchant && $product->getSeller()?->getId() === $user->getId()) {
            return $product;
        }

        return null;
    }
}
