<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\Shared\UsesApiSessionTrait;
use App\Exception\ApiRequestException;
use App\Security\ApiLoginAuthenticator;
use App\Security\FrontAuthenticationManager;
use App\Service\Api\GreenGoodiesApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Supprime le compte courant côté API puis invalide la session locale du front.
 */
final class DeleteAction extends AbstractController
{
    use UsesApiSessionTrait;

    #[Route('/mon-compte/supprimer', name: 'front_account_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        Request $request,
        GreenGoodiesApiClient $apiClient,
        FrontAuthenticationManager $frontAuthenticationManager,
        TokenStorageInterface $tokenStorage,
    ): Response {
        // La suppression de compte est sécurisée par CSRF car elle détruit des données persistées.
        if (!$this->isCsrfTokenValid('front_account_delete', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La requête est invalide.');

            return $this->redirectToRoute('front_account_show');
        }

        $jwt = $this->getJwtFromSession($request);

        if ($jwt === null) {
            return $this->redirectToLogin($request, $frontAuthenticationManager);
        }

        try {
            $apiClient->deleteAccount($jwt);
        } catch (ApiRequestException $exception) {
            if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                return $this->redirectToLogin($request, $frontAuthenticationManager);
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
}
