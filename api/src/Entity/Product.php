<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\ApiState\MerchantProductsProvider;
use App\ApiState\ProductProcessor;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente un produit du catalogue public et du catalogue commerçant.
 */
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['product:read', 'brand:read']],
        ),
        new GetCollection(
            normalizationContext: ['groups' => ['product:read', 'brand:read']],
        ),
        new GetCollection(
            uriTemplate: '/merchant/products',
            normalizationContext: ['groups' => ['product:read', 'brand:read']],
            security: "is_granted('ROLE_USER')",
            provider: MerchantProductsProvider::class,
        ),
        new Post(
            denormalizationContext: ['groups' => ['product:write']],
            normalizationContext: ['groups' => ['product:read', 'brand:read']],
            security: "is_granted('ROLE_USER')",
            processor: ProductProcessor::class,
        ),
    ],
)]
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products', indexes: [new ORM\Index(name: 'idx_product_published', columns: ['is_published'])])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'Ce produit existe déjà.')]
class Product
{
    use TimestampableTrait;

    #[ApiProperty(identifier: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(identifier: true)]
    #[ORM\Column(length: 255, unique: true)]
    #[Assert\Length(max: 255)]
    private string $slug = '';

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $seller = null;

    #[Groups(['product:read', 'product:write'])]
    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Brand $brand = null;

    #[Groups(['product:read', 'product:write'])]
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name = '';

    #[Groups(['product:read', 'product:write'])]
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $shortDescription = '';

    #[Groups(['product:read', 'product:write'])]
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $description = '';

    #[Groups(['product:read', 'product:write'])]
    #[ORM\Column]
    #[Assert\Positive]
    private int $priceCents = 0;

    #[Groups(['product:read', 'product:write'])]
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $imagePath = '';

    #[Groups(['product:read'])]
    #[ORM\Column(options: ['default' => true])]
    private bool $isPublished = true;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: OrderItem::class)]
    private Collection $orderItems;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
        $this->markAsCreated();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function changeSlug(string $slug): self
    {
        $this->slug = trim($slug);
        $this->touch();

        return $this;
    }

    public function setSlug(string $slug): self
    {
        return $this->changeSlug($slug);
    }

    public function getSeller(): ?User
    {
        return $this->seller;
    }

    public function assignSeller(?User $seller): self
    {
        // Le rattachement vendeur sert à la fois au front et à la route commerçant filtrée par clé API.
        $this->seller = $seller;

        if ($seller !== null && !$seller->getProducts()->contains($this)) {
            $seller->addProduct($this);
        }

        $this->touch();

        return $this;
    }

    public function setSeller(?User $seller): self
    {
        return $this->assignSeller($seller);
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function changeBrand(Brand $brand): self
    {
        // La marque est obligatoire sur chaque produit pour répondre au besoin de référencement.
        $this->brand = $brand;
        $this->touch();

        return $this;
    }

    public function setBrand(?Brand $brand): self
    {
        if ($brand === null) {
            $this->brand = null;
            $this->touch();

            return $this;
        }

        return $this->changeBrand($brand);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): self
    {
        $this->name = trim($name);
        $this->touch();

        return $this;
    }

    public function setName(string $name): self
    {
        return $this->rename($name);
    }

    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function changeShortDescription(string $shortDescription): self
    {
        $this->shortDescription = trim($shortDescription);
        $this->touch();

        return $this;
    }

    public function setShortDescription(string $shortDescription): self
    {
        return $this->changeShortDescription($shortDescription);
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function changeDescription(string $description): self
    {
        $this->description = trim($description);
        $this->touch();

        return $this;
    }

    public function setDescription(string $description): self
    {
        return $this->changeDescription($description);
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function changePriceCents(int $priceCents): self
    {
        $this->priceCents = $priceCents;
        $this->touch();

        return $this;
    }

    public function setPriceCents(int $priceCents): self
    {
        return $this->changePriceCents($priceCents);
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function changeImagePath(string $imagePath): self
    {
        $normalizedImagePath = trim($imagePath);

        // Les chemins d'assets publics sont normalisés avec un slash initial pour être exploitables côté front.
        if ($normalizedImagePath !== '' && str_starts_with($normalizedImagePath, 'assets/')) {
            $normalizedImagePath = sprintf('/%s', $normalizedImagePath);
        }

        $this->imagePath = $normalizedImagePath;
        $this->touch();

        return $this;
    }

    public function setImagePath(string $imagePath): self
    {
        return $this->changeImagePath($imagePath);
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function publish(): self
    {
        // Le flag de publication pilote la visibilité dans les catalogues publics et commerçants.
        $this->isPublished = true;
        $this->touch();

        return $this;
    }

    public function unpublish(): self
    {
        $this->isPublished = false;
        $this->touch();

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }
}
