<?php

declare(strict_types=1);

namespace App\ApiState\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Account\CurrentUserView;
use App\ApiResource\Auth\RegisterInput;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Transforme le payload d'inscription en compte persisté puis en vue utilisateur.
 */
final readonly class RegisterUserProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CurrentUserView
    {
        if (!$data instanceof RegisterInput) {
            throw new BadRequestHttpException('Payload d’inscription invalide.');
        }

        // L'unicité de l'email est vérifiée explicitement pour garder une erreur métier lisible côté front.
        if ($this->userRepository->findOneBy(['email' => mb_strtolower(trim($data->email))]) !== null) {
            throw new ConflictHttpException('Cette adresse email est déjà utilisée.');
        }

        $user = new User($data->email, $data->firstName, $data->lastName);
        $user
            ->acceptTerms()
            ->setPassword($this->passwordHasher->hashPassword($user, $data->password));

        // La persistance ne s'effectue qu'une fois le mot de passe haché et les CGU acceptées.
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return CurrentUserView::fromUser($user);
    }
}
