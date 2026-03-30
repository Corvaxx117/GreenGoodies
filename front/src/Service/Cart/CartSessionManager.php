<?php

declare(strict_types=1);

namespace App\Service\Cart;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Gère le panier côté front en session à partir d'un simple couple slug -> quantité.
 */
final class CartSessionManager
{
    public const SESSION_KEY = 'front.cart_items';

    public function getQuantity(SessionInterface $session, string $slug): int
    {
        return $this->getQuantities($session)[$slug] ?? 0;
    }

    public function upsert(SessionInterface $session, string $slug, int $quantity): void
    {
        $items = $this->getQuantities($session);

        if ($quantity <= 0) {
            unset($items[$slug]);
        } else {
            $items[$slug] = $quantity;
        }

        $this->storeQuantities($session, $items);
    }

    public function clear(SessionInterface $session): void
    {
        $session->remove(self::SESSION_KEY);
    }

    public function hasItems(SessionInterface $session): bool
    {
        return $this->getQuantities($session) !== [];
    }

    /**
     * @return list<array{slug: string, quantity: int}>
     */
    public function toOrderPayload(SessionInterface $session): array
    {
        $payload = [];

        foreach ($this->getQuantities($session) as $slug => $quantity) {
            $payload[] = [
                'slug' => $slug,
                'quantity' => $quantity,
            ];
        }

        return $payload;
    }

    /**
     * Construit la vue du panier à partir des produits encore présents dans le catalogue.
     *
     * @param list<array<string, mixed>> $catalog
     *
     * @return array<string, mixed>
     */
    public function buildView(SessionInterface $session, array $catalog): array
    {
        $quantities = $this->getQuantities($session);
        $productsBySlug = [];

        foreach ($catalog as $product) {
            if (!is_array($product) || !is_string($product['slug'] ?? null) || $product['slug'] === '') {
                continue;
            }

            $productsBySlug[$product['slug']] = $product;
        }

        $items = [];
        $normalizedQuantities = [];
        $itemCount = 0;
        $totalCents = 0;

        foreach ($quantities as $slug => $quantity) {
            $product = $productsBySlug[$slug] ?? null;

            // Les lignes pointant vers un produit absent du catalogue sont écartées du panier session.
            if (!is_array($product)) {
                continue;
            }

            $lineTotalCents = max(0, (int) ($product['priceCents'] ?? 0)) * $quantity;
            $normalizedQuantities[$slug] = $quantity;
            $itemCount += $quantity;
            $totalCents += $lineTotalCents;

            $items[] = [
                'productSlug' => $slug,
                'name' => (string) ($product['name'] ?? ''),
                'imagePath' => (string) ($product['imagePath'] ?? ''),
                'quantity' => $quantity,
                'unitPriceCents' => max(0, (int) ($product['priceCents'] ?? 0)),
                'lineTotalCents' => $lineTotalCents,
            ];
        }

        if ($normalizedQuantities !== $quantities) {
            $this->storeQuantities($session, $normalizedQuantities);
        }

        return [
            'itemCount' => $itemCount,
            'deliveryCents' => 0,
            'deliveryLabel' => 'Offert',
            'totalCents' => $totalCents,
            'isEmpty' => $items === [],
            'items' => $items,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function getQuantities(SessionInterface $session): array
    {
        $items = $session->get(self::SESSION_KEY, []);

        if (!is_array($items)) {
            return [];
        }

        $normalizedItems = [];

        foreach ($items as $slug => $quantity) {
            if (!is_string($slug) || $slug === '') {
                continue;
            }

            if (!is_int($quantity) || $quantity <= 0) {
                continue;
            }

            $normalizedItems[$slug] = $quantity;
        }

        return $normalizedItems;
    }

    /**
     * @param array<string, int> $items
     */
    private function storeQuantities(SessionInterface $session, array $items): void
    {
        if ($items === []) {
            $session->remove(self::SESSION_KEY);

            return;
        }

        $session->set(self::SESSION_KEY, $items);
    }
}
