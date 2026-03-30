<?php

declare(strict_types=1);

namespace App\ApiState\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Shared\MessageResource;
use App\Entity\User;
use App\Service\CartManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Supprime la commande brouillon qui sert de panier à l'utilisateur courant.
 */
final readonly class ClearCartProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private CartManager $cartManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MessageResource
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentification requise.');
        }

        $this->cartManager->clear($user);

        return new MessageResource('Panier vidé.');
    }
}
