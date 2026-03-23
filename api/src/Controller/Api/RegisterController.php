<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Expose la route d'inscription utilisée par le front pour créer un compte client.
 */
final readonly class RegisterController
{
    public function __construct(
        private ValidatorInterface $validator,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        // Le contrôleur valide d'abord le payload brut avant toute création d'entité.
        $payload = $request->toArray();

        $violations = $this->validator->validate($payload, new Assert\Collection([
            'firstName' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            'lastName' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            'email' => [new Assert\NotBlank(), new Assert\Email()],
            'password' => [new Assert\NotBlank(), new Assert\Length(min: 8, max: 255)],
            'acceptTerms' => [new Assert\IdenticalTo(true)],
        ]));

        if (count($violations) > 0) {
            return new JsonResponse([
                'message' => 'Les données envoyées sont invalides.',
                'errors' => (string) $violations,
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // L'unicité de l'email est vérifiée explicitement pour produire une réponse métier claire.
        if ($this->userRepository->findOneBy(['email' => mb_strtolower((string) $payload['email'])]) !== null) {
            return new JsonResponse([
                'message' => 'Cette adresse email est déjà utilisée.',
            ], JsonResponse::HTTP_CONFLICT);
        }

        $user = new User(
            (string) $payload['email'],
            (string) $payload['firstName'],
            (string) $payload['lastName'],
        );

        $user
            ->acceptTerms()
            ->setPassword($this->passwordHasher->hashPassword($user, (string) $payload['password']));

        // L'utilisateur est persisté uniquement après le hashage du mot de passe.
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
        ], JsonResponse::HTTP_CREATED);
    }
}
