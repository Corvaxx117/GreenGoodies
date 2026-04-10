<?php

declare(strict_types=1);

namespace App\HttpClient\GreenGoodies;

/**
 * Regroupe les appels HTTP liés au catalogue et aux produits commerçant.
 */
final class ProductClient extends AbstractGreenGoodiesClient
{
    /**
     * Retourne la liste complète des produits disponibles dans le catalogue de l'API.
     *
     * @return list<array<string, mixed>>
     */
    public function listProducts(): array
    {
        return $this->unwrapCollection($this->request('GET', '/api/products'));
    }

    /**
     * Retourne le détail d'un produit identifié par son slug.
     *
     * @return array<string, mixed>
     */
    public function getProduct(string $slug): array
    {
        return $this->request('GET', sprintf('/api/products/%s', rawurlencode($slug)));
    }

    /**
     * Crée un nouveau produit dans le catalogue de l'API avec les données fournies.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function createProduct(array $payload, string $jwt): array
    {
        return $this->request('POST', '/api/products', [
            'json' => $payload,
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }

    /**
     * Met à jour un produit existant du commerçant authentifié.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function updateProduct(string $slug, array $payload, string $jwt): array
    {
        return $this->request('PUT', sprintf('/api/products/%s', rawurlencode($slug)), [
            'json' => $payload,
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }
}
