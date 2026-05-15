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

    /**  
     * Le panier session est volontairement minimaliste et ne stocke que les slugs et quantités, sans données métier ni redondance.
     */
    public function getQuantity(SessionInterface $session, string $slug): int
    {
        return $this->getQuantities($session)[$slug] ?? 0;
    }

    /** 
     * L'ajout, la mise à jour et la suppression d'une ligne de panier sont gérés de manière unifiée : une quantité nulle ou négative supprime la ligne.
     */
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

    /**
     * La suppression complète du panier session est également exposée pour être utilisée après la validation de la commande.
     */
    public function clear(SessionInterface $session): void
    {
        $session->remove(self::SESSION_KEY);
    }

    /**
     * Permet de savoir rapidement si le panier session contient des lignes sans avoir à reconstruire la vue complète.
     */
    public function hasItems(SessionInterface $session): bool
    {
        return $this->getQuantities($session) !== [];
    }

    /**
     * Construit le payload de commande à partir du panier session.
     *
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
     * Retourne le contenu du panier session sous la forme d'un tableau slug -> quantité, en filtrant les données invalides pour éviter les erreurs et la corruption du panier.
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
     * Stocke le contenu du panier session à partir d'un tableau slug -> quantité, en filtrant les données invalides pour éviter la corruption du panier.
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
