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

/**
 * Authenticator du front qui délègue la vraie connexion à l'API et conserve le JWT en session.
 */
final class ApiLoginAuthenticator extends AbstractLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'front_login';
    public const SESSION_JWT_KEY = 'front.api_jwt';

    public function __construct(
        private readonly GreenGoodiesApiClient $apiClient,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    // Le front ne gère pas de formulaire de connexion classique, mais une route dédiée qui reçoit les données du formulaire et les transmet à l'API.
    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') === self::LOGIN_ROUTE && $request->isMethod('POST');
    }

    /**
     * Lorsqu'une tentative de connexion est détectée, le front :
     *  - lit les données du formulaire,
     *  - appelle l'API pour authentifier l'utilisateur,
     *  - construit un Passport avec les informations de l'utilisateur exposées par l'API.
     * @throws CustomUserMessageAuthenticationException
     */
    public function authenticate(Request $request): SelfValidatingPassport
    {
        // Le front lit les données du formulaire et mémorise l'email pour le réaffichage éventuel.
        $data = $request->getPayload()->all();
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        try {
            $authentication = $this->apiClient->authenticate($email, $password);
        } catch (ApiRequestException $exception) {
            // Seul un 401 correspond à de mauvais identifiants ; le reste est un problème technique.
            if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                throw new CustomUserMessageAuthenticationException('Identifiants incorrects');
            }

            throw new CustomUserMessageAuthenticationException($exception->getMessage());
        }

        // L'utilisateur Symfony du front est une projection du profil renvoyé par l'API.
        $frontUser = FrontUser::fromApiPayload($authentication['user']);
        $request->attributes->set(self::SESSION_JWT_KEY, $authentication['token']);

        // Le Passport est auto-validant car l'authentification a déjà été vérifiée auprès de l'API,
        // et contient un UserBadge avec une closure qui retourne l'utilisateur projeté.
        return new SelfValidatingPassport(
            new UserBadge(
                $frontUser->getUserIdentifier(),
                static fn() => $frontUser,
            ),
            [
                new CsrfTokenBadge('front_login', (string) ($data['_token'] ?? '')),
            ],
        );
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        // Le JWT est conservé en session pour les appels ultérieurs vers l'API protégée.
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
