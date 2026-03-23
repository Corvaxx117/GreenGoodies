<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Encapsule une erreur métier ou technique rencontrée lors d'un appel HTTP vers l'API.
 */
final class ApiRequestException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly array $payload = [],
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
