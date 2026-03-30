<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CustomerOrder;
use App\Entity\User;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Fournit les requêtes de lecture dédiées à l'historique de commandes.
 *
 * @extends ServiceEntityRepository<CustomerOrder>
 */
final class CustomerOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerOrder::class);
    }

    /**
     * @return list<CustomerOrder>
     */
    public function findLatestValidatedForUser(User $user, int $limit = 10): array
    {
        /** @var list<CustomerOrder> $orders */
        // Le compte utilisateur affiche uniquement les commandes déjà validées, triées par date décroissante.
        $orders = $this->createQueryBuilder('customerOrder')
            ->andWhere('customerOrder.user = :user')
            ->andWhere('customerOrder.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', OrderStatus::Validated)
            ->orderBy('customerOrder.validatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $orders;
    }

}
