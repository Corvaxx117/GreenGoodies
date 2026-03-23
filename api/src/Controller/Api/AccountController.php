<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\CustomerOrder;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Retourne les données agrégées nécessaires à l'écran "Mon compte" du front.
 */
final readonly class AccountController
{
    #[Route('/api/account', name: 'api_account', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        #[CurrentUser] User $user,
        CustomerOrderRepository $customerOrderRepository,
    ): JsonResponse {
        // L'écran front récupère en un seul appel le profil, l'état API et les dernières commandes validées.
        return new JsonResponse([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
            ],
            'apiAccessEnabled' => $user->isApiAccessEnabled(),
            'apiKeyPrefix' => $user->getApiKey()?->getKeyPrefix(),
            'orders' => array_map(
                static fn (CustomerOrder $order): array => [
                    'reference' => $order->getReference(),
                    'validatedAt' => $order->getValidatedAt()?->format(DATE_ATOM),
                    'totalCents' => $order->getTotalCents(),
                ],
                $customerOrderRepository->findLatestValidatedForUser($user, 10),
            ),
        ]);
    }
}
