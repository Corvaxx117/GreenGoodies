<?php

declare(strict_types=1);

namespace App\ApiResource\Cart;

/**
 * Représente une ligne de panier prête à être rendue par le front.
 */
final class CartItemView
{
    public ?string $productSlug = null;

    public string $name = '';

    public ?string $imagePath = null;

    public int $quantity = 0;

    public int $unitPriceCents = 0;

    public int $lineTotalCents = 0;

    /**
     * Transforme une ligne de payload métier en vue sérialisable.
     *
     * @param array<string, mixed> $item
     */
    public static function fromPayload(array $item): self
    {
        $view = new self();
        $view->productSlug = isset($item['productSlug']) && is_string($item['productSlug']) ? $item['productSlug'] : null;
        $view->name = (string) ($item['name'] ?? '');
        $view->imagePath = isset($item['imagePath']) && is_string($item['imagePath']) ? $item['imagePath'] : null;
        $view->quantity = (int) ($item['quantity'] ?? 0);
        $view->unitPriceCents = (int) ($item['unitPriceCents'] ?? 0);
        $view->lineTotalCents = (int) ($item['lineTotalCents'] ?? 0);

        return $view;
    }
}
