<?php

declare(strict_types=1);

namespace App\HttpClient\GreenGoodies;

use App\Exception\ApiRequestException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Centralise la communication HTTP avec l'API GreenGoodies et les règles d'erreur partagées.
 */
abstract class AbstractGreenGoodiesClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(API_BASE_URL)%')]
        private readonly string $apiBaseUrl,
    ) {
    }

    /**
     * Effectue une requête HTTP vers l'API GreenGoodies, gère les erreurs et retourne le payload décodé.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    protected function request(string $method, string $uri, array $options = []): array
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

        // Les autres erreurs HTTP sont traitées après décodage pour extraire un message métier si possible.
        try {
            $payload = $content !== '' ? json_decode($content, true, 512, JSON_THROW_ON_ERROR) : [];
        } catch (\JsonException) {
            throw new ApiRequestException(
                'La réponse du service GreenGoodies est invalide. Merci de réessayer plus tard.',
                Response::HTTP_BAD_GATEWAY,
            );
        }

        if ($statusCode >= Response::HTTP_BAD_REQUEST) {
            $message = $this->extractErrorMessage(is_array($payload) ? $payload : [], $statusCode);

            throw new ApiRequestException($message, $statusCode, is_array($payload) ? $payload : []);
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * Extrait un message d'erreur utilisateur à partir du payload de réponse de l'API.
     *
     * @param array<string, mixed> $payload
     */
    protected function extractErrorMessage(array $payload, int $statusCode): string
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
     * Construit les en-têtes nécessaires pour appeler une route protégée par JWT.
     *
     * @return array<string, string>
     */
    protected function authenticatedHeaders(string $jwt): array
    {
        return [
            'Authorization' => sprintf('Bearer %s', $jwt),
        ];
    }

    /**
     * Déplie une collection Hydra ou retourne le payload tel quel si ce n'est pas une collection API Platform.
     *
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    protected function unwrapCollection(array $payload): array
    {
        if (isset($payload['member']) && is_array($payload['member'])) {
            /** @var list<array<string, mixed>> $member */
            $member = array_values(array_filter(
                $payload['member'],
                static fn (mixed $item): bool => is_array($item),
            ));

            return $member;
        }

        if (isset($payload['hydra:member']) && is_array($payload['hydra:member'])) {
            /** @var list<array<string, mixed>> $member */
            $member = array_values(array_filter(
                $payload['hydra:member'],
                static fn (mixed $item): bool => is_array($item),
            ));

            return $member;
        }

        return [];
    }
}
