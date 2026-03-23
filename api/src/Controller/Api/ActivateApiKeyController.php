<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\ApiKey;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Active l'accès API commerçant et génère une nouvelle clé API côté utilisateur.
 */
final readonly class ActivateApiKeyController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/me/api-key/activate', name: 'api_me_api_key_activate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        // La clé n'est produite en clair qu'au moment de l'activation.
        $plainKey = sprintf('GGK_%s', strtoupper(bin2hex(random_bytes(20))));

        $user->enableApiAccess();

        // Une clé existante est régénérée, sinon une nouvelle entité ApiKey est créée.
        if ($user->getApiKey() instanceof ApiKey) {
            $user->getApiKey()
                ->rotate($plainKey, true);
        } else {
            $apiKey = new ApiKey($user, $plainKey, true);
            $this->entityManager->persist($apiKey);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Le front reçoit la clé complète une seule fois ; seul son hash reste ensuite en base.
        return new JsonResponse([
            'message' => 'Accès API activé.',
            'apiKey' => $plainKey,
        ]);
    }
}
