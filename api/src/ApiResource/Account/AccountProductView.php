<?php

declare(strict_types=1);

namespace App\ApiResource\Account;

use App\Entity\Product;

/**
 * Représente un produit créé par l'utilisateur pour l'écran "Mon compte".
 */
final class AccountProductView
{
    public string $slug = '';

    public string $name = '';

    public string $brand = '';

    public string $imagePath = '';

    public int $priceCents = 0;

    /**
     * Réduit un produit à la vue légère nécessaire à la liste "Mes produits".
     */
    public static function fromProduct(Product $product): self
    {
        $view = new self();
        $view->slug = $product->getSlug();
        $view->name = $product->getName();
        $view->brand = $product->getBrand();
        $view->imagePath = $product->getImagePath();
        $view->priceCents = $product->getPriceCents();

        return $view;
    }
}
