<?php

declare(strict_types=1);

namespace App\ApiState\Product;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\ProductRepository;

/**
 * Retourne le catalogue public publié.
 */
final readonly class PublishedProductsProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        return $this->productRepository->findPublishedCatalog();
    }
}
