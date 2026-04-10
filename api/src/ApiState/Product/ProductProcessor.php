<?php

declare(strict_types=1);

namespace App\ApiState\Product;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Merchant;
use App\Entity\Product;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Complète les règles métier de création d'un produit avant la persistance Doctrine d'API Platform.
 */
final readonly class ProductProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private SluggerInterface $slugger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Les autres ressources éventuelles sont redéléguées sans traitement spécifique.
        if (!$data instanceof Product) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->getUser();

        if (!$user instanceof Merchant) {
            throw new AccessDeniedException('Vous devez être connecté avec un compte commerçant pour gérer un produit.');
        }

        // Lors d'une mise à jour, le produit existant doit déjà appartenir au commerçant authentifié.
        if ($data->getId() !== null) {
            if (!$data->getSeller() instanceof Merchant || $data->getSeller()->getId() !== $user->getId()) {
                throw new AccessDeniedHttpException('Vous ne pouvez modifier que vos propres produits.');
            }
        }

        // Le vendeur est déduit du JWT si le front ne l'a pas fourni explicitement.
        if ($data->getSeller() === null) {
            $data->assignSeller($user);
        }

        // Le slug est généré automatiquement à partir du nom quand il n'est pas saisi.
        if ($data->getSlug() === '') {
            $data->changeSlug($this->slugger->slug($data->getName())->lower()->toString());
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
