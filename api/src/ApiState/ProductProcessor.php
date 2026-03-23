<?php

declare(strict_types=1);

namespace App\ApiState;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Product;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\String\Slugger\SluggerInterface;

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
        if (!$data instanceof Product) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Vous devez être connecté pour ajouter un produit.');
        }

        if ($data->getSeller() === null) {
            $data->assignSeller($user);
        }

        if ($data->getSlug() === '') {
            $data->changeSlug($this->slugger->slug($data->getName())->lower()->toString());
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
