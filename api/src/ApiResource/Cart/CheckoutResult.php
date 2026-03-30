<?php

declare(strict_types=1);

namespace App\ApiResource\Cart;

use App\Entity\CustomerOrder;

/**
 * Transporte le résultat de la validation d'un panier en commande confirmée.
 */
final class CheckoutResult
{
    public string $message = 'Commande validée avec succès.';

    public string $reference = '';

    public ?string $validatedAt = null;

    public int $totalCents = 0;

    /**
     * Déduit le payload de confirmation à partir de la commande validée.
     */
    public static function fromOrder(CustomerOrder $order): self
    {
        $view = new self();
        $view->reference = $order->getReference();
        $view->validatedAt = $order->getValidatedAt()?->format(DATE_ATOM);
        $view->totalCents = $order->getTotalCents();

        return $view;
    }
}
