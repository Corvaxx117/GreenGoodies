<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Mutualise la gestion des dates de création et de mise à jour des entités métier.
 */
trait TimestampableTrait
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    protected function markAsCreated(): void
    {
        // Lors d'une création manuelle, createdAt et updatedAt démarrent au même instant.
        $now = new \DateTimeImmutable();

        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function touch(): void
    {
        // Toute modification métier peut appeler touch() pour refléter immédiatement la mise à jour.
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        // Le hook Doctrine garantit des timestamps valides même si markAsCreated() n'a pas été appelé.
        $now = new \DateTimeImmutable();

        $this->createdAt ??= $now;
        $this->updatedAt ??= $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        // Chaque update Doctrine rafraîchit automatiquement la date de dernière modification.
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
