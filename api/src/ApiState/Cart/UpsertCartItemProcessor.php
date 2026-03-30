<?php

declare(strict_types=1);

namespace App\ApiState\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Cart\CartItemInput;
use App\ApiResource\Cart\CartView;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Service\CartManager;
use App\Service\CartPayloadFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Ajoute, met à jour ou retire une ligne de panier via une opération API Platform.
 */
final readonly class UpsertCartItemProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ProductRepository $productRepository,
        private CartManager $cartManager,
        private CartPayloadFactory $cartPayloadFactory,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CartView
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentification requise.');
        }

        if (!$data instanceof CartItemInput) {
            throw new BadRequestHttpException('Payload panier invalide.');
        }

        $slug = (string) ($uriVariables['slug'] ?? '');
        $product = $this->productRepository->findOnePublishedBySlug($slug);

        // Le slug désigne un produit du catalogue public ; l'opération échoue s'il n'existe pas.
        if ($product === null) {
            throw new NotFoundHttpException('Produit introuvable.');
        }

        $order = $this->cartManager->updateItem($user, $product, $data->quantity ?? 0);

        return CartView::fromPayload($this->cartPayloadFactory->create($order));
    }
}
