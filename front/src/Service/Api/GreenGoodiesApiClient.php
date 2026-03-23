<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Exception\ApiRequestException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client HTTP dédié à la communication avec l'API GreenGoodies, avec gestion centralisée des erreurs et extraction de messages utilisateur.
 */
final readonly class GreenGoodiesApiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(API_BASE_URL)%')]
        private string $apiBaseUrl,
    ) {}

    /**
     * Retourne la liste complète des produits disponibles dans le catalogue de l'API.
     * @return list<array<string, mixed>>
     */
    public function listProducts(): array
    {
        return $this->unwrapCollection($this->request('GET', '/api/products'));
    }

    /**
     * Retourne le détail d'un produit identifié par son slug, ou lance une ApiRequestException en cas d'erreur.
     * @return array<string, mixed>
     */
    public function getProduct(string $slug): array
    {
        return $this->request('GET', sprintf('/api/products/%s', rawurlencode($slug)));
    }

    /**
     * Authentifie un utilisateur et retourne le jeton JWT ainsi que les informations utilisateur.
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
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $token),
            ],
        ]);

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    /**
     * Enregistre un nouvel utilisateur avec les données fournies et retourne le résultat de l'opération.
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
     * Retourne la liste complète des marques disponibles dans le catalogue de l'API.
     * @return list<array<string, mixed>>
     */
    public function listBrands(): array
    {
        return $this->unwrapCollection($this->request('GET', '/api/brands'));
    }

    /**
     * Crée un nouveau produit dans le catalogue de l'API avec les données fournies et retourne le résultat de l'opération.
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
     * Retourne le panier de l'utilisateur authentifié, ou lance une ApiRequestException en cas d'erreur.
     * @return array<string, mixed>
     */
    public function getCart(string $jwt): array
    {
        return $this->request('GET', '/api/cart', [
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }

    /**
     * Met à jour la quantité d'un produit dans le panier de l'utilisateur authentifié, ou lance une ApiRequestException en cas d'erreur.
     * @return array<string, mixed>
     */
    public function updateCartItem(string $slug, int $quantity, string $jwt): array
    {
        return $this->request('POST', sprintf('/api/cart/items/%s', rawurlencode($slug)), [
            'json' => [
                'quantity' => $quantity,
            ],
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }

    /**
     * Vide le panier de l'utilisateur authentifié, ou lance une ApiRequestException en cas d'erreur.
     * @return array<string, mixed>
     */
    public function clearCart(string $jwt): array
    {
        return $this->request('POST', '/api/cart/clear', [
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }

    /**
     * Vide le panier de l'utilisateur authentifié, ou lance une ApiRequestException en cas d'erreur.
     * @return array<string, mixed>
     */
    public function checkoutCart(string $jwt): array
    {
        return $this->request('POST', '/api/cart/checkout', [
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }

    /**
     * Retourne les informations du compte de l'utilisateur authentifié, ou lance une ApiRequestException en cas d'erreur.
     * @return array<string, mixed>
     */
    public function getAccount(string $jwt): array
    {
        return $this->request('GET', '/api/account', [
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }

    /**
     * Active l'accès API pour l'utilisateur authentifié, ou lance une ApiRequestException en cas d'erreur.
     * @return array<string, mixed>
     */
    public function activateApiAccess(string $jwt): array
    {
        return $this->request('POST', '/api/me/api-key/activate', [
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }

    /**
     * Désactive l'accès API pour l'utilisateur authentifié, ou lance une ApiRequestException en cas d'erreur.
     * @return array<string, mixed>
     */
    public function deactivateApiAccess(string $jwt): array
    {
        return $this->request('POST', '/api/me/api-key/deactivate', [
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }

    /**
     * Supprime le compte de l'utilisateur authentifié, ou lance une ApiRequestException en cas d'erreur.
     * @return array<string, mixed>
     */
    public function deleteAccount(string $jwt): array
    {
        return $this->request('DELETE', '/api/me', [
            'headers' => $this->authenticatedHeaders($jwt),
        ]);
    }

    /**
     * Effectue une requête HTTP vers l'API GreenGoodies, gère les erreurs et retourne le payload décodé.
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        $options['headers'] = array_merge([
            'Accept' => 'application/json',
        ], $options['headers'] ?? []);

        // Les exceptions de transport sont transformées en ApiRequestException avec un message utilisateur générique.
        try {
            $response = $this->httpClient->request(
                $method,
                sprintf('%s%s', rtrim($this->apiBaseUrl, '/'), $uri),
                $options,
            );

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface) {
            throw new ApiRequestException(
                'Le service GreenGoodies est temporairement indisponible. Merci de réessayer dans quelques instants.',
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }
        // Les autres exceptions HTTP sont traitées après tentative de décodage du payload pour extraire un message d'erreur utilisateur.
        try {
            $payload = $content !== '' ? json_decode($content, true, 512, JSON_THROW_ON_ERROR) : [];
        } catch (\JsonException) {
            throw new ApiRequestException(
                'La réponse du service GreenGoodies est invalide. Merci de réessayer plus tard.',
                Response::HTTP_BAD_GATEWAY,
            );
        }
        // Les réponses avec un code HTTP d'erreur (4xx ou 5xx) sont transformées en ApiRequestException avec un message extrait du payload ou un message générique selon le code.
        if ($statusCode >= Response::HTTP_BAD_REQUEST) {
            $message = $this->extractErrorMessage(is_array($payload) ? $payload : [], $statusCode);
            throw new ApiRequestException($message, $statusCode, is_array($payload) ? $payload : []);
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * Extrait un message d'erreur utilisateur à partir du payload de réponse de l'API, ou retourne un message générique selon le code HTTP.
     * @param array<string, mixed> $payload
     */
    private function extractErrorMessage(array $payload, int $statusCode): string
    {
        $message = $payload['message']
            ?? $payload['detail']
            ?? $payload['description']
            ?? $payload['hydra:description']
            ?? null;

        if (is_string($message) && $message !== '') {
            return $message;
        }

        if (isset($payload['violations']) && is_array($payload['violations'])) {
            $messages = [];

            foreach ($payload['violations'] as $violation) {
                if (!is_array($violation) || !isset($violation['message']) || !is_string($violation['message'])) {
                    continue;
                }

                $messages[] = $violation['message'];
            }

            if ($messages !== []) {
                return implode(' ', array_unique($messages));
            }
        }

        return match (true) {
            $statusCode === Response::HTTP_UNAUTHORIZED => 'Authentification refusée.',
            $statusCode === Response::HTTP_FORBIDDEN => 'Vous n’êtes pas autorisé à effectuer cette action.',
            $statusCode === Response::HTTP_NOT_FOUND => 'La ressource demandée est introuvable.',
            $statusCode === Response::HTTP_UNPROCESSABLE_ENTITY => 'Les données envoyées sont invalides.',
            $statusCode >= Response::HTTP_INTERNAL_SERVER_ERROR => 'Le service GreenGoodies est temporairement indisponible. Merci de réessayer plus tard.',
            default => 'La requête API a échoué.',
        };
    }

    /**
     * Déplie une collection Hydra ou API Platform, ou retourne le payload tel quel s'il ne correspond pas au format attendu.
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    private function unwrapCollection(array $payload): array
    {
        $collection = $payload['hydra:member'] ?? $payload['member'] ?? $payload;

        return array_values(is_array($collection) ? $collection : []);
    }

    /**
     * Construit les en-têtes d'authentification pour un utilisateur authentifié avec un JWT, ou lance une ApiRequestException si le JWT est invalide.
     * @return array<string, string>
     */
    private function authenticatedHeaders(string $jwt): array
    {
        return [
            'Authorization' => sprintf('Bearer %s', $jwt),
        ];
    }
}
