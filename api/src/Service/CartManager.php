<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Porte la logique métier du panier, représenté en base par une commande en statut draft.
 */
final readonly class CartManager
{
    public function __construct(
        private CustomerOrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getDraftOrder(User $user): ?CustomerOrder
    {
        return $this->orderRepository->findDraftForUser($user);
    }

    public function updateItem(User $user, Product $product, int $quantity): ?CustomerOrder
    {
        // Chaque utilisateur ne possède qu'un seul panier actif, retrouvé par son draft courant.
        $order = $this->getDraftOrder($user);

        if ($quantity <= 0) {
            // Une quantité nulle retire la ligne du panier au lieu de créer une valeur incohérente.
            if ($order === null) {
                return null;
            }

            $item = $order->findItemForProduct($product);

            if ($item === null) {
                return $order;
            }

            $order->removeItem($item);

            if ($order->getItems()->isEmpty()) {
                // Un panier sans ligne n'est pas conservé en base.
                $this->entityManager->remove($order);
                $this->entityManager->flush();

                return null;
            }

            $this->entityManager->flush();

            return $order;
        }

        if ($order === null) {
            // Le premier ajout au panier crée automatiquement la commande brouillon.
            $order = new CustomerOrder($user);
            $this->entityManager->persist($order);
        }

        $order->addItem($product, $quantity);
        $this->entityManager->flush();

        return $order;
    }

    public function clear(User $user): void
    {
        $order = $this->getDraftOrder($user);

        if ($order === null) {
            return;
        }

        $this->entityManager->remove($order);
        $this->entityManager->flush();
    }

    public function checkout(User $user): CustomerOrder
    {
        $order = $this->getDraftOrder($user);

        // Une validation sans article reste interdite au niveau métier.
        if ($order === null || $order->getItems()->isEmpty()) {
            throw new \DomainException('Votre panier est vide.');
        }

        $order->validate();
        $this->entityManager->flush();

        return $order;
    }
}
