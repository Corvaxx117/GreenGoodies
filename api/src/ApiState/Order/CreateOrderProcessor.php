<?php

declare(strict_types=1);

namespace App\ApiState\Order;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Order\CreateOrderInput;
use App\ApiResource\Order\OrderResult;
use App\Entity\CustomerOrder;
use App\Entity\User;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Crée une commande validée à partir des lignes envoyées par le panier session du front.
 */
final readonly class CreateOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ProductRepository $productRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderResult
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentification requise.');
        }

        if (!$data instanceof CreateOrderInput) {
            throw new BadRequestHttpException('Payload de commande invalide.');
        }

        $order = new CustomerOrder($user);

        foreach ($data->items as $item) {
            $slug = trim((string) ($item['slug'] ?? ''));
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($slug === '' || $quantity <= 0) {
                throw new BadRequestHttpException('Une ligne de commande est invalide.');
            }

            $product = $this->productRepository->findOnePublishedBySlug($slug);

            if ($product === null) {
                throw new NotFoundHttpException(sprintf('Produit introuvable : %s.', $slug));
            }

            $order->addItem($product, $quantity);
        }

        $order->validate();

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return OrderResult::fromOrder($order);
    }
}
