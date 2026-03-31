<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Représente un commerçant pouvant proposer ses produits et activer une clé API.
 */
#[ORM\Entity]
class Merchant extends User
{
    #[ORM\Column(options: ['default' => false])]
    private bool $apiAccessEnabled = false;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: ApiKey::class, cascade: ['persist', 'remove'])]
    private ?ApiKey $apiKey = null;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\OneToMany(mappedBy: 'seller', targetEntity: Product::class)]
    private Collection $products;

    /**
     * @param list<string> $roles
     */
    public function __construct(string $email, string $firstName, string $lastName, array $roles = [])
    {
        parent::__construct($email, $firstName, $lastName, $roles);
        $this->products = new ArrayCollection();
    }

    public function getRoles(): array
    {
        return $this->normalizeRoles([...$this->getStoredRoles(), 'ROLE_USER', 'ROLE_MERCHANT']);
    }

    public function isMerchant(): bool
    {
        return true;
    }

    public function isApiAccessEnabled(): bool
    {
        return $this->apiAccessEnabled;
    }

    public function enableApiAccess(): self
    {
        $this->apiAccessEnabled = true;
        $this->touch();

        return $this;
    }

    public function disableApiAccess(): self
    {
        $this->apiAccessEnabled = false;
        $this->touch();

        return $this;
    }

    public function getApiKey(): ?ApiKey
    {
        return $this->apiKey;
    }

    public function attachApiKey(ApiKey $apiKey): self
    {
        $this->apiKey = $apiKey;

        if ($apiKey->getUser() !== $this) {
            $apiKey->changeUser($this);
        }

        $this->touch();

        return $this;
    }

    public function removeApiKey(): self
    {
        $this->apiKey = null;
        $this->touch();

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }

        if ($product->getSeller() !== $this) {
            $product->assignSeller($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): self
    {
        $this->products->removeElement($product);

        return $this;
    }

    public function getAccountType(): string
    {
        return 'merchant';
    }
}
