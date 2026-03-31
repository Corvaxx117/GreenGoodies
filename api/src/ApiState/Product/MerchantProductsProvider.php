<?php

declare(strict_types=1);

namespace App\ApiState\Product;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Merchant;
use App\Repository\ProductRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Fournit à la route commerçant uniquement les produits appartenant au propriétaire de la clé API.
 */
final readonly class MerchantProductsProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository $productRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        // L'authenticator par clé API place directement le propriétaire authentifié dans le contexte Security.
        $user = $this->security->getUser();

        if (!$user instanceof Merchant) {
            throw new AccessDeniedException('Clé API invalide.');
        }

        // Aucun identifiant n'est passé dans l'URL : le périmètre vient uniquement de l'utilisateur authentifié.
        return $this->productRepository->findPublishedBySeller($user);
    }
}
