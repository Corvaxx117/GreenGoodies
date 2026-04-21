<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CustomerOrder;
use App\Entity\User;
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
    public function findByUser(User $user): array
    {
        /** @var list<CustomerOrder> $orders */
        // Le compte utilisateur expose l'historique complet du propriétaire, trié du plus récent au plus ancien.
        $orders = $this->createQueryBuilder('customerOrder')
            ->andWhere('customerOrder.user = :user')
            ->setParameter('user', $user)
            ->orderBy('customerOrder.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $orders;
    }
}
