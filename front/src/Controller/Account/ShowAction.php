<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\Shared\UsesApiSessionTrait;
use App\Exception\ApiRequestException;
use App\HttpClient\GreenGoodies\UserClient;
use App\Security\FrontAuthenticationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Affiche l'écran "Mon compte" à partir de l'agrégat retourné par l'API.
 */
final class ShowAction extends AbstractController
{
    use UsesApiSessionTrait;

    public function __construct(
        private readonly UserClient $userClient,
        private readonly FrontAuthenticationManager $frontAuthenticationManager,
    ) {
    }

    #[Route('/mon-compte', name: 'front_account_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): Response
    {
        $jwt = $this->getJwtFromSession($request);

        if ($jwt === null) {
            return $this->redirectToLogin($request, $this->frontAuthenticationManager);
        }

        try {
            // L'écran compte est un agrégat API : profil, commandes et état de l'accès API.
            $account = $this->userClient->getAccount($jwt);
        } catch (ApiRequestException $exception) {
            if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                return $this->redirectToLogin($request, $this->frontAuthenticationManager);
            }

            // En cas d'erreur, le front garde la page visible avec un état minimal.
            $this->addFlash('error', $exception->getMessage());
            $account = [
                'apiAccessEnabled' => false,
                'apiKeyPrefix' => null,
                'orders' => [],
                'products' => [],
            ];
        }

        return $this->render('account/show.html.twig', [
            'account' => $account,
        ]);
    }
}
