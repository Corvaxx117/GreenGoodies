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
        $plainKey = sprintf('GGK_%s', strtoupper(bin2hex(random_bytes(20))));

        $user->enableApiAccess();

        if ($user->getApiKey() instanceof ApiKey) {
            $user->getApiKey()
                ->rotate($plainKey, true);
        } else {
            $apiKey = new ApiKey($user, $plainKey, true);
            $this->entityManager->persist($apiKey);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Accès API activé.',
            'apiKey' => $plainKey,
        ]);
    }
}
