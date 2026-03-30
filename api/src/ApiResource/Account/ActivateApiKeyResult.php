<?php

declare(strict_types=1);

namespace App\ApiResource\Account;

/**
 * Transporte le résultat de l'activation de l'accès API commerçant avec la clé en clair affichée une seule fois.
 */
final class ActivateApiKeyResult
{
    public function __construct(
        public string $message = '',
        public string $apiKey = '',
    ) {
    }
}
