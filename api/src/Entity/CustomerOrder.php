<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Enum\OrderStatus;
use App\Repository\CustomerOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Représente une commande utilisateur persistée, validée ou annulée.
 */
#[ORM\Entity(repositoryClass: CustomerOrderRepository::class)]
#[ORM\Table(name: 'customer_orders', indexes: [new ORM\Index(name: 'idx_customer_order_status', columns: ['status'])])]
#[ORM\HasLifecycleCallbacks]
class CustomerOrder
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $reference;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(enumType: OrderStatus::class, length: 16)]
    private OrderStatus $status = OrderStatus::Draft;

    #[ORM\Column]
    private int $totalCents = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    /**
     * @var Collection<int, OrderItem>
     */
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
