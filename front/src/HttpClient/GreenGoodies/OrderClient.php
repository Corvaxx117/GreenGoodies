<?php

declare(strict_types=1);

namespace App\HttpClient\GreenGoodies;

/**
 * Regroupe les appels HTTP liés à la création des commandes.
 */
final class OrderClient extends AbstractGreenGoodiesClient
{
    /**
     * Retourne les commandes du compte courant.
     *
     * @return list<array<string, mixed>>
     */
    public function listCurrentUserOrders(string $jwt): array
    {
        return $this->unwrapCollection($this->request('GET', '/api/users/me/orders', [
            'headers' => $this->authenticatedHeaders($jwt),
        ]));
    }

    /**
     * Crée une commande validée à partir des lignes du panier session envoyées par le front.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function createOrder(array $payload, string $jwt): array
    {
        return $this->request('POST', '/api/orders', [
            'json' => $payload,
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }
}
