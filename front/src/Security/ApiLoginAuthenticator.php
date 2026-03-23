<?php

declare(strict_types=1);

namespace App\Security;

use App\Exception\ApiRequestException;
use App\Service\Api\GreenGoodiesApiClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class ApiLoginAuthenticator extends AbstractLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'front_login';
    public const SESSION_JWT_KEY = 'front.api_jwt';

    public function __construct(
        private readonly GreenGoodiesApiClient $apiClient,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') === self::LOGIN_ROUTE && $request->isMethod('POST');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $data = $request->getPayload()->all();
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        try {
            $authentication = $this->apiClient->authenticate($email, $password);
        } catch (ApiRequestException) {
            throw new CustomUserMessageAuthenticationException('Identifiants incorrects');
        }

        $frontUser = FrontUser::fromApiPayload($authentication['user']);
        $request->attributes->set(self::SESSION_JWT_KEY, $authentication['token']);

        return new SelfValidatingPassport(
            new UserBadge(
                $frontUser->getUserIdentifier(),
                static fn () => $frontUser,
            ),
            [
                new CsrfTokenBadge('front_login', (string) ($data['_token'] ?? '')),
            ],
        );
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        $request->getSession()->set(self::SESSION_JWT_KEY, $request->attributes->get(self::SESSION_JWT_KEY));

        return new RedirectResponse($this->urlGenerator->generate('front_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new RedirectResponse($this->getLoginUrl($request));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
