<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\ApiResource\Auth\RegisterInput;
use App\ApiState\Account\ActivateApiKeyProcessor;
use App\ApiState\Account\CurrentUserProvider;
use App\ApiState\Account\DeactivateApiKeyProcessor;
use App\ApiState\Account\DeleteCurrentUserProcessor;
use App\ApiState\Auth\RegisterUserProcessor;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente un compte client "lambda", racine de l'héritage des utilisateurs applicatifs.
 */
#[ApiResource(
    shortName: 'User',
    normalizationContext: ['groups' => ['user:read']],
    operations: [
        new Post(
            uriTemplate: '/users',
            input: RegisterInput::class,
            output: self::class,
            read: false,
            processor: RegisterUserProcessor::class,
            security: "is_granted('PUBLIC_ACCESS')",
            openapi: new OpenApiOperation(
                tags: ['Users'],
                summary: 'Créer un compte',
                description: 'Inscription d’un utilisateur GreenGoodies.',
                security: [],
            ),
        ),
        new Get(
            uriTemplate: '/users/me',
            provider: CurrentUserProvider::class,
            security: "is_granted('ROLE_USER')",
            openapi: new OpenApiOperation(
                tags: ['Users'],
                summary: 'Récupérer le profil courant',
                description: 'Retourne les informations du compte authentifié.',
                security: [['JWT' => []]],
            ),
        ),
        new Delete(
            uriTemplate: '/users/me',
            read: false,
            deserialize: false,
            validate: false,
            output: false,
            status: 204,
            processor: DeleteCurrentUserProcessor::class,
            security: "is_granted('ROLE_USER')",
            openapi: new OpenApiOperation(
                tags: ['Users'],
                summary: 'Supprimer le compte courant',
                description: 'Supprime le compte authentifié ainsi que ses données associées.',
                security: [['JWT' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/users/me/api-key/activate',
            read: false,
            input: false,
            output: self::class,
            processor: ActivateApiKeyProcessor::class,
            normalizationContext: ['groups' => ['user:read', 'user:api-key:read']],
            security: "is_granted('ROLE_MERCHANT')",
            openapi: new OpenApiOperation(
                tags: ['Merchant API'],
                summary: 'Activer la clé API commerçant',
                description: 'Active l’accès API commerçant et retourne la clé API en clair une seule fois.',
                security: [['JWT' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/users/me/api-key/deactivate',
            read: false,
            input: false,
            output: false,
            status: 204,
            processor: DeactivateApiKeyProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
            openapi: new OpenApiOperation(
                tags: ['Merchant API'],
                summary: 'Désactiver la clé API commerçant',
                description: 'Désactive l’accès API commerçant du compte authentifié.',
                security: [['JWT' => []]],
            ),
        ),
    ],
)]
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
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user:read'])]
    private string $email;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    #[Groups(['user:read'])]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['user:read'])]
    private string $firstName;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['user:read'])]
    private string $lastName;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $termsAcceptedAt = null;

    /**
     * @var Collection<int, CustomerOrder>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: CustomerOrder::class, orphanRemoval: true)]
    private Collection $orders;

    private ?string $plainApiKey = null;

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

    #[Groups(['user:read'])]
    public function getApiAccessEnabled(): bool
    {
        return $this->isApiAccessEnabled();
    }

    #[Groups(['user:read'])]
    public function getApiKeyPrefix(): ?string
    {
        return $this->getApiKey()?->getKeyPrefix();
    }

    #[Groups(['user:read'])]
    public function getAccountType(): string
    {
        return 'customer';
    }

    public function revealPlainApiKey(?string $plainApiKey): self
    {
        $this->plainApiKey = $plainApiKey !== null ? trim($plainApiKey) : null;

        return $this;
    }

    #[Groups(['user:api-key:read'])]
    public function getPlainApiKey(): ?string
    {
        return $this->plainApiKey;
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
