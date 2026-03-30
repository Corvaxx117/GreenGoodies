<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\Shared\UsesApiSessionTrait;
use App\Exception\ApiRequestException;
use App\Security\FrontAuthenticationManager;
use App\Service\Api\GreenGoodiesApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Active ou désactive l'accès API commerçant du compte courant.
 */
final class ToggleApiAccessAction extends AbstractController
{
    use UsesApiSessionTrait;

    #[Route('/mon-compte/acces-api', name: 'front_account_api_access_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        Request $request,
        GreenGoodiesApiClient $apiClient,
        FrontAuthenticationManager $frontAuthenticationManager,
    ): Response
    {
        // L'activation de clé API est protégée côté front par CSRF avant tout appel HTTP.
        if (!$this->isCsrfTokenValid('front_account_api_access', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requête est invalide.');

            return $this->redirectToRoute('front_account_show');
        }

        $jwt = $this->getJwtFromSession($request);

        if ($jwt === null) {
            return $this->redirectToLogin($request, $frontAuthenticationManager);
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
                return $this->redirectToLogin($request, $frontAuthenticationManager);
            }

            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('front_account_show');
    }
}
