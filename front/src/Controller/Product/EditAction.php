<?php

declare(strict_types=1);

namespace App\Controller\Product;

use App\Exception\ApiRequestException;
use App\Form\ProductFormType;
use App\HttpClient\GreenGoodies\ProductClient;
use App\Security\ApiLoginAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Affiche le formulaire d'édition produit puis délègue la mise à jour à l'API REST.
 */
final class EditAction extends AbstractController
{
    public function __construct(
        private readonly ProductClient $productClient,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    #[Route('/mes-produits/{slug}/modifier', name: 'front_product_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MERCHANT')]
    public function __invoke(string $slug, Request $request): Response
    {
        try {
            $product = $this->productClient->getProduct($slug);
        } catch (ApiRequestException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('front_account_show');
        }

        $form = $this->formFactory->createNamed('', ProductFormType::class, [
            'brand' => (string) ($product['brand'] ?? ''),
            'name' => (string) ($product['name'] ?? ''),
            'slug' => (string) ($product['slug'] ?? $slug),
            'shortDescription' => (string) ($product['shortDescription'] ?? ''),
            'description' => (string) ($product['description'] ?? ''),
            'priceCents' => (int) ($product['priceCents'] ?? 0),
            'imagePath' => (string) ($product['imagePath'] ?? ''),
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

                // Le payload reste aligné sur le contrat de l'API pour la création et la mise à jour.
                $payload = [
                    'brand' => trim((string) ($data['brand'] ?? '')),
                    'name' => trim((string) ($data['name'] ?? '')),
                    'slug' => trim((string) ($data['slug'] ?? '')),
                    'shortDescription' => trim((string) ($data['shortDescription'] ?? '')),
                    'description' => trim((string) ($data['description'] ?? '')),
                    'priceCents' => (int) ($data['priceCents'] ?? 0),
                    'imagePath' => trim((string) ($data['imagePath'] ?? '')),
                ];

                $updatedProduct = $this->productClient->updateProduct($slug, $payload, $jwt);
                $updatedSlug = is_string($updatedProduct['slug'] ?? null) ? $updatedProduct['slug'] : $slug;

                $this->addFlash('success', 'Produit mis à jour avec succès.');

                return $this->redirectToRoute('front_product_show', [
                    'slug' => $updatedSlug,
                ]);
            } catch (ApiRequestException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('dashboard/product/new.html.twig', [
            'productForm' => $form,
            'pageTitle' => 'Modifier un produit',
            'pageIntro' => 'Le formulaire front enverra les données à l’API REST pour mettre à jour votre produit.',
            'submitLabel' => 'Enregistrer les modifications',
        ]);
    }
}
