<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ApiRequestException;
use App\Form\ProductFormType;
use App\Security\ApiLoginAuthenticator;
use App\Service\Api\GreenGoodiesApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ProductManagementController extends AbstractController
{
    #[Route('/mes-produits/nouveau', name: 'front_product_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, GreenGoodiesApiClient $apiClient, FormFactoryInterface $formFactory): Response
    {
        $form = $formFactory->createNamed('', ProductFormType::class, null, [
            'brands' => $apiClient->listBrands(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $jwt = (string) $request->getSession()->get(ApiLoginAuthenticator::SESSION_JWT_KEY, '');

            if ($jwt === '') {
                $this->addFlash('error', 'Votre session a expiré. Merci de vous reconnecter.');

                return $this->redirectToRoute('front_login');
            }

            try {
                $data = $form->getData();
                $payload = [
                    'brand' => (string) ($data['brand'] ?? ''),
                    'name' => trim((string) ($data['name'] ?? '')),
                    'slug' => trim((string) ($data['slug'] ?? '')),
                    'shortDescription' => trim((string) ($data['shortDescription'] ?? '')),
                    'description' => trim((string) ($data['description'] ?? '')),
                    'priceCents' => (int) ($data['priceCents'] ?? 0),
                    'imagePath' => trim((string) ($data['imagePath'] ?? '')),
                ];

                $product = $apiClient->createProduct($payload, $jwt);
                $slug = is_string($product['slug'] ?? null) ? $product['slug'] : null;

                if (($slug === null || $slug === '') && isset($product['@id']) && is_string($product['@id'])) {
                    $slug = basename($product['@id']);
                }

                if ($slug === null || $slug === '') {
                    $this->addFlash('error', 'Le produit a été créé, mais la réponse API ne contient pas de slug exploitable.');

                    return $this->redirectToRoute('front_product_new');
                }

                $this->addFlash('success', 'Produit ajouté avec succès.');

                return $this->redirectToRoute('front_product_show', [
                    'slug' => $slug,
                ]);
            } catch (ApiRequestException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('dashboard/product/new.html.twig', [
            'productForm' => $form,
        ]);
    }
}
