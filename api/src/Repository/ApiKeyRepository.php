<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Fournit les méthodes d'accès spécialisées aux clés API commerçant.
 *
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
        // Le préfixe est utile pour afficher ou tracer une clé sans révéler sa valeur complète.
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
        // La recherche principale d'authentification se fait sur le hash de la clé reçue.
        return $this->createQueryBuilder('apiKey')
            ->andWhere('apiKey.hashedKey = :hashedKey')
            ->andWhere('apiKey.enabled = :enabled')
            ->setParameter('hashedKey', $hashedKey)
            ->setParameter('enabled', true)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
