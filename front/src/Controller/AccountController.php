<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ApiRequestException;
use App\Security\ApiLoginAuthenticator;
use App\Service\Api\GreenGoodiesApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gère l'écran "Mon compte" et les actions sensibles liées au profil utilisateur.
 */
final class AccountController extends AbstractController
{
    #[Route('/mon-compte', name: 'front_account_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(Request $request, GreenGoodiesApiClient $apiClient): Response
    {
        $jwt = $this->getJwtFromSession($request);

        if ($jwt === null) {
            return $this->redirectToLogin();
        }

        try {
            // L'écran compte est un agrégat API : profil, commandes et état de l'accès API.
            $account = $apiClient->getAccount($jwt);
        } catch (ApiRequestException $exception) {
            if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                return $this->redirectToLogin();
            }

            // En cas d'erreur, le front garde la page visible avec un état minimal.
            $this->addFlash('error', $exception->getMessage());
            $account = [
                'apiAccessEnabled' => false,
                'apiKeyPrefix' => null,
                'orders' => [],
            ];
        }

        return $this->render('account/show.html.twig', [
            'account' => $account,
        ]);
    }

    #[Route('/mon-compte/acces-api', name: 'front_account_api_access_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggleApiAccess(Request $request, GreenGoodiesApiClient $apiClient): Response
    {
        // L'activation de clé API est protégée côté front par CSRF avant tout appel HTTP.
        if (!$this->isCsrfTokenValid('front_account_api_access', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requête est invalide.');

            return $this->redirectToRoute('front_account_show');
        }

        $jwt = $this->getJwtFromSession($request);

        if ($jwt === null) {
            return $this->redirectToLogin();
        }

        $enabled = $request->request->getBoolean('enabled');

        try {
            // Le booléen courant détermine si l'on active une nouvelle clé ou si l'on désactive l'accès existant.
            $response = $enabled
                ? $apiClient->deactivateApiAccess($jwt)
                : $apiClient->activateApiAccess($jwt);

            $message = (string) ($response['message'] ?? ($enabled ? 'Accès API désactivé.' : 'Accès API activé.'));

            if (!$enabled && isset($response['apiKey']) && is_string($response['apiKey'])) {
                $message = sprintf('%s Clé API : %s', $message, $response['apiKey']);
            }

            $this->addFlash('success', $message);
        } catch (ApiRequestException $exception) {
            if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                return $this->redirectToLogin();
            }

            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('front_account_show');
    }

    #[Route('/mon-compte/supprimer', name: 'front_account_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(
        Request $request,
        GreenGoodiesApiClient $apiClient,
        TokenStorageInterface $tokenStorage,
    ): Response {
        // La suppression de compte est sécurisée par CSRF car elle détruit des données persistées.
        if (!$this->isCsrfTokenValid('front_account_delete', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requête est invalide.');

            return $this->redirectToRoute('front_account_show');
        }

        $jwt = $this->getJwtFromSession($request);

        if ($jwt === null) {
            return $this->redirectToLogin();
        }

        try {
            $apiClient->deleteAccount($jwt);
        } catch (ApiRequestException $exception) {
            if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                return $this->redirectToLogin();
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('front_account_show');
        }

        // Après suppression côté API, la session locale Symfony du front est explicitement invalidée.
        $tokenStorage->setToken(null);
        $request->getSession()->remove(ApiLoginAuthenticator::SESSION_JWT_KEY);
        $request->getSession()->invalidate();

        return $this->redirectToRoute('front_home');
    }

    private function getJwtFromSession(Request $request): ?string
    {
        $jwt = (string) $request->getSession()->get(ApiLoginAuthenticator::SESSION_JWT_KEY, '');

        return $jwt !== '' ? $jwt : null;
    }

    private function redirectToLogin(): Response
    {
        // Le front recentre toujours l'utilisateur vers la reconnexion quand le JWT n'est plus exploitable.
        $this->addFlash('error', 'Votre session API a expiré. Merci de vous reconnecter.');

        return $this->redirectToRoute('front_login');
    }
}
