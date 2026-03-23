<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\ApiKeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class MerchantApiKeyAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private ApiKeyRepository $apiKeyRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api/merchant');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $plainKey = trim((string) $request->headers->get('X-API-Key'));

        if ($plainKey === '') {
            throw new CustomUserMessageAuthenticationException('Clé API manquante.');
        }

        $apiKey = $this->apiKeyRepository->findEnabledByHashedKey(hash('sha256', $plainKey));

        if ($apiKey === null || !$apiKey->getUser()->isApiAccessEnabled()) {
            throw new CustomUserMessageAuthenticationException('Clé API invalide ou désactivée.');
        }

        $apiKey->markAsUsed();
        $this->entityManager->flush();

        $user = $apiKey->getUser();

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), static fn () => $user),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'message' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'message' => 'Authentification API requise.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
