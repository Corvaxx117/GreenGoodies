<?php

declare(strict_types=1);

namespace App\ApiResource\Account;

use App\Entity\CustomerOrder;

/**
 * Représente une ligne d'historique de commande dans l'écran "Mon compte".
 */
final class AccountOrderView
{
    public string $reference;

    public ?string $validatedAt = null;

    public int $totalCents;

    /**
     * Transforme une commande validée en payload léger pour le front.
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
