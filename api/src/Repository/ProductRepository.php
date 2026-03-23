<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Fournit les requêtes ciblées sur les catalogues public et commerçant.
 *
 * @extends ServiceEntityRepository<Product>
 */
final class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return list<Product>
     */
    public function findPublishedCatalog(int $limit = 12): array
    {
        /** @var list<Product> $products */
        // Le catalogue public n'expose que les produits publiés.
        $products = $this->createQueryBuilder('product')
            ->andWhere('product.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('product.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $products;
    }

    public function findOnePublishedBySlug(string $slug): ?Product
    {
        // Les fiches produit du front sont accessibles par slug public uniquement.
        return $this->createQueryBuilder('product')
            ->andWhere('product.slug = :slug')
            ->andWhere('product.isPublished = :published')
            ->setParameter('slug', $slug)
            ->setParameter('published', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Product>
     */
    public function findPublishedBySeller(User $seller): array
    {
        /** @var list<Product> $products */
        // La route commerçant filtre les produits sur leur propriétaire métier.
        $products = $this->createQueryBuilder('product')
            ->andWhere('product.seller = :seller')
            ->andWhere('product.isPublished = :published')
            ->setParameter('seller', $seller)
            ->setParameter('published', true)
            ->orderBy('product.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $products;
    }
}
