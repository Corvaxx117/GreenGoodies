<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Exception\ApiRequestException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GreenGoodiesApiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(API_BASE_URL)%')]
        private string $apiBaseUrl,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listProducts(): array
    {
        return $this->unwrapCollection($this->request('GET', '/api/products'));
    }

    /**
     * @return array<string, mixed>
     */
    public function getProduct(string $slug): array
    {
        return $this->request('GET', sprintf('/api/products/%s', rawurlencode($slug)));
    }

    /**
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
     * @return list<array<string, mixed>>
     */
    public function listBrands(): array
    {
        return $this->unwrapCollection($this->request('GET', '/api/brands'));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function createProduct(array $payload, string $jwt): array
    {
        return $this->request('POST', '/api/products', [
            'json' => $payload,
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $jwt),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        $options['headers'] = array_merge([
            'Accept' => 'application/json',
        ], $options['headers'] ?? []);

        $response = $this->httpClient->request(
            $method,
            sprintf('%s%s', rtrim($this->apiBaseUrl, '/'), $uri),
            $options,
        );

        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);
        try {
            $payload = $content !== '' ? json_decode($content, true, 512, JSON_THROW_ON_ERROR) : [];
        } catch (\JsonException) {
            $payload = [];
        }

        if ($statusCode >= Response::HTTP_BAD_REQUEST) {
            $message = $this->extractErrorMessage(is_array($payload) ? $payload : []);
            throw new ApiRequestException($message, $statusCode, is_array($payload) ? $payload : []);
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractErrorMessage(array $payload): string
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

        return 'La requête API a échoué.';
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    private function unwrapCollection(array $payload): array
    {
        $collection = $payload['hydra:member'] ?? $payload['member'] ?? $payload;

        return array_values(is_array($collection) ? $collection : []);
    }
}
