<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Api\GreenGoodiesApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    #[Route('/produits/{slug}', name: 'front_product_show', methods: ['GET'])]
    public function show(string $slug, GreenGoodiesApiClient $apiClient): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $apiClient->getProduct($slug),
        ]);
    }
}
