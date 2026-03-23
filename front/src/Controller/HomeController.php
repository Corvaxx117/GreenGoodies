<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ApiRequestException;
use App\Service\Api\GreenGoodiesApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gère la page d'accueil publique et le chargement du catalogue depuis l'API.
 */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'front_home', methods: ['GET'])]
    public function index(GreenGoodiesApiClient $apiClient): Response
    {
        $products = [];
        $catalogUnavailable = false;

        try {
            // Le front n'accède jamais à la base : le catalogue est toujours récupéré via l'API REST.
            $products = $apiClient->listProducts();
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
