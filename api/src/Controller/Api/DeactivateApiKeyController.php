<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Désactive l'accès API commerçant du compte courant.
 */
final readonly class DeactivateApiKeyController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/me/api-key/deactivate', name: 'api_me_api_key_deactivate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        // Le compte et la clé doivent être désactivés de concert pour fermer complètement l'accès.
        $user->disableApiAccess();

        if ($user->getApiKey() !== null) {
            $user->getApiKey()->disable();
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Accès API désactivé.',
        ]);
    }
}
