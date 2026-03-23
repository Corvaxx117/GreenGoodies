<?php

declare(strict_types=1);

namespace App\Exception;

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
