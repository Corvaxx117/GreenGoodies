<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\ApiState\Product\CurrentUserProductsProvider;
use App\ApiState\Product\MerchantProductsProvider;
use App\ApiState\Product\ProductItemProvider;
use App\ApiState\Product\ProductProcessor;
use App\ApiState\Product\PublishedProductsProvider;
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
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']],
    operations: [
        new GetCollection(
            // Get collection → /api/products
            provider: PublishedProductsProvider::class,
            openapi: new OpenApiOperation(
                tags: ['Catalog'],
                summary: 'Lister les produits du catalogue',
                description: 'Retourne les produits publiés visibles sur le catalogue public.',
            ),
        ),
        new GetCollection(
            uriTemplate: '/users/me/products',
            security: "is_granted('ROLE_MERCHANT')",
            provider: CurrentUserProductsProvider::class,
            openapi: new OpenApiOperation(
                tags: ['Catalog'],
                summary: 'Lister les produits du compte courant',
                description: 'Retourne les produits du commerçant authentifié côté front.',
                security: [['JWT' => []]],
            ),
        ),
        new GetCollection(
            uriTemplate: '/products/mine',
            security: "is_granted('ROLE_MERCHANT')",
            provider: MerchantProductsProvider::class,
            openapi: new OpenApiOperation(
                tags: ['Merchant API'],
                summary: 'Lister les produits du commerçant',
                description: 'Retourne uniquement les produits publiés du propriétaire de la clé API `X-API-Key`.',
                security: [['merchantApiKey' => []]],
            ),
        ),
        new Get(
            // Get item → /api/products/{slug}
            provider: ProductItemProvider::class,
            openapi: new OpenApiOperation(
                tags: ['Catalog'],
                summary: 'Voir un produit',
                description: 'Retourne le détail d’un produit publié, ou d’un produit du commerçant authentifié.',
            ),
        ),
        new Post(
            // Post collection → /api/products
            security: "is_granted('ROLE_MERCHANT')",
            processor: ProductProcessor::class,
            openapi: new OpenApiOperation(
                tags: ['Catalog'],
                summary: 'Créer un produit',
                description: 'Ajoute un produit pour l’utilisateur authentifié côté front.',
                security: [['JWT' => []]],
            ),
        ),
        new Put(
            // Put item → /api/products/{slug}
            security: "is_granted('ROLE_MERCHANT')",
            processor: ProductProcessor::class,
            openapi: new OpenApiOperation(
                tags: ['Catalog'],
                summary: 'Modifier un produit',
                description: 'Met à jour un produit appartenant au commerçant authentifié.',
                security: [['JWT' => []]],
            ),
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
    private ?Merchant $seller = null;

    #[Groups(['product:read', 'product:write'])]
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $brand = '';

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

    public function getSeller(): ?Merchant
    {
        return $this->seller;
    }

    public function assignSeller(?Merchant $seller): self
    {
        // Le rattachement vendeur sert à la fois au front et à la route commerçant filtrée par clé API.
        $this->seller = $seller;

        if ($seller !== null && !$seller->getProducts()->contains($this)) {
            $seller->addProduct($this);
        }

        $this->touch();

        return $this;
    }

    public function setSeller(?Merchant $seller): self
    {
        return $this->assignSeller($seller);
    }

    public function getBrand(): string
    {
        return $this->brand;
    }

    public function changeBrand(string $brand): self
    {
        $this->brand = trim($brand);
        $this->touch();

        return $this;
    }

    public function setBrand(string $brand): self
    {
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

    #[Groups(['product:read'])]
    public function getIsPublished(): bool
    {
        return $this->isPublished();
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
