<?php

declare(strict_types=1);

namespace App\HttpClient\GreenGoodies;

use App\Exception\ApiRequestException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Regroupe les appels HTTP liés à l'authentification, au compte et aux actions utilisateur.
 */
final class UserClient extends AbstractGreenGoodiesClient
{
    /**
     * Authentifie un utilisateur et retourne le jeton JWT ainsi que les informations utilisateur.
     *
     * @return array{token: string, user: array<string, mixed>}
     */
    public function authenticate(string $email, string $password): array
    {
        $authentication = $this->request('POST', '/auth', [
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ]);

        $token = (string) ($authentication['token'] ?? '');

        if ($token === '') {
            throw new ApiRequestException('Jeton JWT manquant dans la réponse API.', Response::HTTP_BAD_GATEWAY);
        }

        $user = $this->request('GET', '/api/me', [
            'headers' => $this->authenticatedHeaders($token),
        ]);

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    /**
     * Enregistre un nouvel utilisateur avec les données fournies.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function register(array $payload): array
    {
        return $this->request('POST', '/api/register', [
            'json' => $payload,
        ]);
    }

    /**
     * Retourne les informations du compte de l'utilisateur authentifié.
     *
     * @return array<string, mixed>
     */
    public function getAccount(string $jwt): array
    {
        return $this->request('GET', '/api/account', [
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }

    /**
     * Active l'accès API pour l'utilisateur authentifié.
     *
     * @return array<string, mixed>
     */
    public function activateApiAccess(string $jwt): array
    {
        return $this->request('POST', '/api/me/api-key/activate', [
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }

    /**
     * Désactive l'accès API pour l'utilisateur authentifié.
     *
     * @return array<string, mixed>
     */
    public function deactivateApiAccess(string $jwt): array
    {
        return $this->request('POST', '/api/me/api-key/deactivate', [
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }

    /**
     * Supprime le compte de l'utilisateur authentifié.
     *
     * @return array<string, mixed>
     */
    public function deleteAccount(string $jwt): array
    {
        return $this->request('DELETE', '/api/me', [
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }
}
