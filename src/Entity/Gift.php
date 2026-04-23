<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GiftStatus;
use App\Repository\GiftRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GiftRepository::class)]
#[ORM\Table(name: 'gifts')]
class Gift
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $giftName;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $pointCost;

    #[ORM\Column(type: 'integer')]
    private int $stock;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    #[ORM\Column(length: 32, enumType: GiftStatus::class)]
    private GiftStatus $status;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $giftName, string $pointCost, int $stock, GiftStatus $status = GiftStatus::Active)
    {
        $this->giftName = $giftName;
        $this->pointCost = $pointCost;
        $this->stock = $stock;
        $this->status = $status;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGiftName(): string
    {
        return $this->giftName;
    }

    public function getPointCost(): string
    {
        return $this->pointCost;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function getStatus(): GiftStatus
    {
        return $this->status;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function isActive(): bool
    {
        return $this->status === GiftStatus::Active;
    }

    public function reserveOne(): void
    {
        if ($this->stock <= 0) {
            throw new \RuntimeException('Gift is out of stock.');
        }

        --$this->stock;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
