<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Enum\OrderStatus;

/**
 * Transforme une commande brouillon en payload JSON simple pour le front.
 */
final class CartPayloadFactory
{
    /**
     * @return array<string, mixed>
     */
    public function create(?CustomerOrder $order): array
    {
        $items = [];
        $itemCount = 0;

        if ($order !== null) {
            // Chaque ligne est réduite à un format directement exploitable par le Twig du front.
            /** @var OrderItem $item */
            foreach ($order->getItems() as $item) {
                $itemCount += $item->getQuantity();

                $items[] = [
                    'productSlug' => $item->getProduct()?->getSlug(),
                    'name' => $item->getProductName(),
                    'imagePath' => $item->getProduct()?->getImagePath(),
                    'quantity' => $item->getQuantity(),
                    'unitPriceCents' => $item->getUnitPriceCents(),
                    'lineTotalCents' => $item->getLineTotalCents(),
                ];
            }
        }

        return [
            // Même sans panier actif, le front reçoit une structure stable pour simplifier son rendu.
            'reference' => $order?->getReference(),
            'status' => $order?->getStatus()->value ?? OrderStatus::Draft->value,
            'itemCount' => $itemCount,
            'deliveryCents' => 0,
            'deliveryLabel' => 'Offert',
            'totalCents' => $order?->getTotalCents() ?? 0,
            'isEmpty' => $items === [],
            'items' => $items,
        ];
    }
}
