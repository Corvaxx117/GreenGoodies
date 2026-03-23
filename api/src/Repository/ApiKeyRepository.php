<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiKey>
 */
final class ApiKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiKey::class);
    }

    public function findEnabledByPrefix(string $prefix): ?ApiKey
    {
        return $this->createQueryBuilder('apiKey')
            ->andWhere('apiKey.keyPrefix = :prefix')
            ->andWhere('apiKey.enabled = :enabled')
            ->setParameter('prefix', strtoupper($prefix))
            ->setParameter('enabled', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findEnabledByHashedKey(string $hashedKey): ?ApiKey
    {
        return $this->createQueryBuilder('apiKey')
            ->andWhere('apiKey.hashedKey = :hashedKey')
            ->andWhere('apiKey.enabled = :enabled')
            ->setParameter('hashedKey', $hashedKey)
            ->setParameter('enabled', true)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
