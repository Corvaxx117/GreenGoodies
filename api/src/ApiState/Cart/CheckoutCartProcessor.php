<?php

declare(strict_types=1);

namespace App\ApiState\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Cart\CheckoutResult;
use App\Entity\User;
use App\Service\CartManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Valide le panier courant et retourne les informations de confirmation de commande.
 */
final readonly class CheckoutCartProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private CartManager $cartManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CheckoutResult
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentification requise.');
        }

        try {
            // La validation traduit l'intention métier du front en transition draft -> validated.
            $order = $this->cartManager->checkout($user);
        } catch (\DomainException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        return CheckoutResult::fromOrder($order);
    }
}
