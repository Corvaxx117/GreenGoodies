<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\ApiResource\Order\CreateOrderInput;
use App\ApiState\Order\CreateOrderProcessor;
use App\ApiState\Order\CurrentUserOrdersProvider;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\OrderStatus;
use App\Repository\CustomerOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Représente une commande utilisateur persistée, validée ou annulée.
 */
#[ApiResource(
    shortName: 'Order',
    normalizationContext: ['groups' => ['order:read']],
    operations: [
        new Get(
            uriTemplate: '/orders/{reference}',
            security: "object.getUser() == user",
            openapi: new OpenApiOperation(
                tags: ['Orders'],
                summary: 'Voir une commande',
                description: 'Retourne une commande du compte authentifié à partir de sa référence.',
                security: [['JWT' => []]],
            ),
        ),
        new GetCollection(
            uriTemplate: '/users/me/orders',
            provider: CurrentUserOrdersProvider::class,
            security: "is_granted('ROLE_USER')",
            openapi: new OpenApiOperation(
                tags: ['Orders'],
                summary: 'Lister les commandes du compte courant',
                description: 'Retourne les commandes de l’utilisateur authentifié.',
                security: [['JWT' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/orders',
            input: CreateOrderInput::class,
            output: self::class,
            read: false,
            processor: CreateOrderProcessor::class,
            security: "is_granted('ROLE_USER')",
            openapi: new OpenApiOperation(
                tags: ['Orders'],
                summary: 'Créer une commande',
                description: 'Transforme le panier session du front en commande validée côté API.',
                security: [['JWT' => []]],
            ),
        ),
    ],
)]
#[ORM\Entity(repositoryClass: CustomerOrderRepository::class)]
#[ORM\Table(name: 'customer_orders', indexes: [new ORM\Index(name: 'idx_customer_order_status', columns: ['status'])])]
#[ORM\HasLifecycleCallbacks]
class CustomerOrder
{
    use TimestampableTrait {
        getCreatedAt as private getTimestampCreatedAt;
    }

    #[ApiProperty(identifier: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['order:read'])]
    #[ApiProperty(identifier: true)]
    #[ORM\Column(length: 32, unique: true)]
    private string $reference;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[Groups(['order:read'])]
    #[ORM\Column(enumType: OrderStatus::class, length: 16)]
    private OrderStatus $status = OrderStatus::Draft;

    #[Groups(['order:read'])]
    #[ORM\Column]
    private int $totalCents = 0;

    #[Groups(['order:read'])]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[Groups(['order:read'])]
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct(User $user, ?string $reference = null)
    {
        $this->user = $user;
        $this->reference = $reference ?? self::generateReference();
        $this->items = new ArrayCollection();
        $this->markAsCreated();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getTotalCents(): int
    {
        return $this->totalCents;
    }

    #[Groups(['order:read'])]
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->getTimestampCreatedAt();
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function findItemForProduct(Product $product): ?OrderItem
    {
        // Cette recherche évite de dupliquer une ligne pour un produit déjà présent dans le panier.
        foreach ($this->items as $item) {
            if ($item->matchesProduct($product)) {
                return $item;
            }
        }

        return null;
    }

    public function addItem(Product $product, int $quantity): self
    {
        // L'ajout fusionne les quantités lorsque le produit existe déjà dans la commande.
        if (($item = $this->findItemForProduct($product)) instanceof OrderItem) {
            $item->changeQuantity($quantity);
            $this->recalculateTotal();

            return $this;
        }

        $this->items->add(new OrderItem($this, $product, $quantity));
        $this->recalculateTotal();

        return $this;
    }

    public function validate(): self
    {
        // Une commande vide ne peut pas franchir l'étape de validation.
        if ($this->items->isEmpty()) {
            throw new \DomainException('Une commande vide ne peut pas être validée.');
        }

        // La validation fige la date métier de confirmation.
        $this->status = OrderStatus::Validated;
        $this->validatedAt = new \DateTimeImmutable();
        $this->recalculateTotal();

        return $this;
    }

    public function cancel(): self
    {
        $this->status = OrderStatus::Cancelled;
        $this->touch();

        return $this;
    }

    public function recalculateTotal(): void
    {
        // Le total est toujours recalculé à partir des lignes pour éviter tout écart de synchronisation.
        $this->totalCents = array_reduce(
            $this->items->toArray(),
            static fn (int $total, OrderItem $item): int => $total + $item->getLineTotalCents(),
            0,
        );

        $this->touch();
    }

    private static function generateReference(): string
    {
        // La référence métier permet d'identifier clairement une commande côté front et dans les échanges.
        return sprintf('GG-%s', strtoupper(bin2hex(random_bytes(6))));
    }
}
