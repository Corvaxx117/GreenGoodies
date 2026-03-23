<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Repository\ApiKeyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApiKeyRepository::class)]
#[ORM\Table(name: 'api_keys')]
#[ORM\HasLifecycleCallbacks]
class ApiKey
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'apiKey')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 16, unique: true)]
    private string $keyPrefix;

    #[ORM\Column(length: 64, unique: true)]
    private string $hashedKey;

    #[ORM\Column(options: ['default' => false])]
    private bool $enabled = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct(User $user, string $plainTextKey, bool $enabled = true)
    {
        $this->changeUser($user);
        $this->rotate($plainTextKey, $enabled);
        $this->markAsCreated();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function changeUser(User $user): self
    {
        $this->user = $user;

        if ($user->getApiKey() !== $this) {
            $user->attachApiKey($this);
        }

        $this->touch();

        return $this;
    }

    public function getKeyPrefix(): string
    {
        return $this->keyPrefix;
    }

    public function getHashedKey(): string
    {
        return $this->hashedKey;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): self
    {
        $this->enabled = true;
        $this->touch();

        return $this;
    }

    public function disable(): self
    {
        $this->enabled = false;
        $this->touch();

        return $this;
    }

    public function rotate(string $plainTextKey, bool $enabled = true): self
    {
        $normalizedKey = trim($plainTextKey);

        $this->keyPrefix = strtoupper(substr($normalizedKey, 0, 16));
        $this->hashedKey = hash('sha256', $normalizedKey);
        $this->enabled = $enabled;
        $this->touch();

        return $this;
    }

    public function matches(string $plainTextKey): bool
    {
        return hash_equals($this->hashedKey, hash('sha256', trim($plainTextKey)));
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markAsUsed(): self
    {
        $this->lastUsedAt = new \DateTimeImmutable();
        $this->touch();

        return $this;
    }
}
