<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente un compte client "lambda", racine de l'héritage des utilisateurs applicatifs.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'account_type', type: 'string', length: 32)]
#[ORM\DiscriminatorMap([
    'customer' => User::class,
    'merchant' => Merchant::class,
])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse email est déjà utilisée.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimestampableTrait;

    /**
     * @var list<string>
     */
    private const DEFAULT_ROLES = [];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $lastName;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $termsAcceptedAt = null;

    /**
     * @var Collection<int, CustomerOrder>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: CustomerOrder::class, orphanRemoval: true)]
    private Collection $orders;

    /**
     * @param list<string> $roles
     */
    public function __construct(string $email, string $firstName, string $lastName, array $roles = self::DEFAULT_ROLES)
    {
        $this->email = mb_strtolower(trim($email));
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->roles = $this->normalizeRoles($roles);
        $this->orders = new \Doctrine\Common\Collections\ArrayCollection();
        $this->markAsCreated();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));
        $this->touch();

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->normalizeRoles([...$this->getStoredRoles(), 'ROLE_USER', 'ROLE_CUSTOMER']);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $this->normalizeRoles($roles);
        $this->touch();

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        $this->touch();

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = trim($firstName);
        $this->touch();

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = trim($lastName);
        $this->touch();

        return $this;
    }

    public function getFullName(): string
    {
        return trim(sprintf('%s %s', $this->firstName, $this->lastName));
    }

    public function getTermsAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->termsAcceptedAt;
    }

    public function acceptTerms(?\DateTimeImmutable $acceptedAt = null): self
    {
        // La date d'acceptation des CGU est conservée à titre de preuve fonctionnelle.
        $this->termsAcceptedAt = $acceptedAt ?? new \DateTimeImmutable();
        $this->touch();

        return $this;
    }

    /**
     * @return Collection<int, CustomerOrder>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(CustomerOrder $order): self
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
        }

        return $this;
    }

    public function isMerchant(): bool
    {
        return false;
    }

    public function isApiAccessEnabled(): bool
    {
        return false;
    }

    public function getApiKey(): ?ApiKey
    {
        return null;
    }

    public function getAccountType(): string
    {
        return 'customer';
    }

    /**
     * @param list<string> $roles
     *
     * @return list<string>
     */
    protected function normalizeRoles(array $roles): array
    {
        $roles = array_map(
            static fn (string $role): string => strtoupper(trim($role)),
            $roles,
        );

        $roles = array_filter($roles);

        return array_values(array_unique($roles));
    }

    /**
     * @return list<string>
     */
    protected function getStoredRoles(): array
    {
        return $this->roles;
    }
}
