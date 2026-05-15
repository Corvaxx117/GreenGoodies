<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\Shared\UsesApiSessionTrait;
use App\Exception\ApiRequestException;
use App\HttpClient\GreenGoodies\OrderClient;
use App\HttpClient\GreenGoodies\ProductClient;
use App\HttpClient\GreenGoodies\UserClient;
use App\Security\FrontAuthenticationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Affiche l'écran "Mon compte" en composant plusieurs ressources de l'API.
 */
final class ShowAction extends AbstractController
{
    use UsesApiSessionTrait;

    public function __construct(
        private readonly UserClient $userClient,
        private readonly OrderClient $orderClient,
        private readonly ProductClient $productClient,
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
            $user = $this->userClient->getCurrentUser($jwt);
            $orders = $this->orderClient->listCurrentUserOrders($jwt);
            $products = $this->isGranted('ROLE_MERCHANT')
                ? $this->productClient->listCurrentUserProducts($jwt)
                : [];

            $account = [
                'user' => $user,
                'apiAccessEnabled' => (bool) ($user['apiAccessEnabled'] ?? false),
                'apiKeyPrefix' => $user['apiKeyPrefix'] ?? null,
                'orders' => $orders,
                'products' => $products,
            ];
        } catch (ApiRequestException $exception) {
            if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                return $this->redirectToLogin($request, $this->frontAuthenticationManager);
            }

            // Le front compose désormais l'écran à partir de plusieurs ressources ; en cas d'échec, il garde un état minimal.
            $this->addFlash('error', $exception->getMessage());
            $account = [
                'user' => [],
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
