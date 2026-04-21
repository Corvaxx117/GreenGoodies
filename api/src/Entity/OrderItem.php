<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente une ligne de commande avec un snapshot du produit au moment de l'achat.
 */
#[ORM\Entity]
#[ORM\Table(name: 'order_items')]
#[ORM\HasLifecycleCallbacks]
class OrderItem
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CustomerOrder $order;

    #[ORM\ManyToOne(inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product;

    #[Groups(['order:read'])]
    #[ORM\Column(length: 255)]
    private string $productName;

    #[Groups(['order:read'])]
    #[ORM\Column]
    private int $unitPriceCents;

    #[Groups(['order:read'])]
    #[ORM\Column]
    #[Assert\Positive]
    private int $quantity;

    #[Groups(['order:read'])]
    #[ORM\Column]
    private int $lineTotalCents;

    public function __construct(CustomerOrder $order, Product $product, int $quantity)
    {
        // La quantité doit rester strictement positive tant que la ligne existe.
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité doit être strictement positive.');
        }

        $this->order = $order;
        $this->product = $product;
        $this->quantity = $quantity;
        $this->syncSnapshot();
        $this->markAsCreated();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): CustomerOrder
    {
        return $this->order;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getUnitPriceCents(): int
    {
        return $this->unitPriceCents;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function changeQuantity(int $quantity): self
    {
        // La suppression d'une ligne se gère au niveau de la commande, pas par quantité négative ou nulle ici.
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité doit être strictement positive.');
        }

        $this->quantity = $quantity;
        $this->syncSnapshot();

        return $this;
    }

    public function getLineTotalCents(): int
    {
        return $this->lineTotalCents;
    }

    public function matchesProduct(Product $product): bool
    {
        return $this->product === $product;
    }

    private function syncSnapshot(): void
    {
        // Le nom et le prix sont copiés depuis le produit pour préserver l'historique même si le produit évolue.
        if ($this->product === null) {
            throw new \LogicException('Une ligne de commande doit référencer un produit au moment de sa création.');
        }

        $this->productName = $this->product->getName();
        $this->unitPriceCents = $this->product->getPriceCents();
        $this->lineTotalCents = $this->unitPriceCents * $this->quantity;
        $this->touch();
    }
}
