<?php

declare(strict_types=1);

namespace App\ApiResource\Shared;

/**
 * Représente une réponse minimaliste de type message pour les commandes qui n'ont rien d'autre à renvoyer.
 */
final class MessageResource
{
    public function __construct(
        public string $message = '',
    ) {
    }
}
