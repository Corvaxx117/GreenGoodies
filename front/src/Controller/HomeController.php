<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Api\GreenGoodiesApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'front_home', methods: ['GET'])]
    public function index(GreenGoodiesApiClient $apiClient): Response
    {
        return $this->render('home/index.html.twig', [
            'products' => $apiClient->listProducts(),
        ]);
    }
}
