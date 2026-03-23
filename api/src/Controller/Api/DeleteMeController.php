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
 * Supprime le compte courant et laisse Doctrine propager la suppression aux données dépendantes.
 */
final readonly class DeleteMeController
{
    #[Route('/api/me', name: 'api_me_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        #[CurrentUser] User $user,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        // Les relations avec cascade/orphanRemoval prennent en charge le nettoyage métier lié au compte.
        $entityManager->remove($user);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Compte supprimé.',
        ]);
    }
}
