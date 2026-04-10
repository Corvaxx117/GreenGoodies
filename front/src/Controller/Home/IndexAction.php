<?php

declare(strict_types=1);

namespace App\Controller\Home;

use App\Exception\ApiRequestException;
use App\HttpClient\GreenGoodies\ProductClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Affiche la page d'accueil publique et charge le catalogue depuis l'API.
 */
final class IndexAction extends AbstractController
{
    public function __construct(
        private readonly ProductClient $productClient,
    ) {
    }

    #[Route('/', name: 'front_home', methods: ['GET'])]
    public function __invoke(): Response
    {
        $products = [];
        $catalogUnavailable = false;

        try {
            // Le front n'accède jamais à la base : le catalogue est toujours récupéré via l'API REST.
            $products = $this->productClient->listProducts();
        } catch (ApiRequestException $exception) {
            // En cas de panne API, la page reste affichable avec un état vide et un message explicite.
            $catalogUnavailable = true;
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->render('home/index.html.twig', [
            'products' => $products,
            'catalogUnavailable' => $catalogUnavailable,
        ]);
    }
}
