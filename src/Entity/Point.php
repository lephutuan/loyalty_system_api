<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PointRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PointRepository::class)]
#[ORM\Table(name: 'points')]
class Point
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Wallet::class, inversedBy: 'points')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Wallet $wallet;

    #[ORM\ManyToOne(targetEntity: Transaction::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Transaction $transaction = null;

    #[ORM\ManyToOne(targetEntity: Redemption::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Redemption $redemption = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $pointAmount;

    #[ORM\Column(length: 255)]
    private string $description;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(Wallet $wallet, string $pointAmount, string $description, ?Transaction $transaction = null, ?Redemption $redemption = null)
    {
        $this->wallet = $wallet;
        $this->pointAmount = $pointAmount;
        $this->description = $description;
        $this->transaction = $transaction;
        $this->redemption = $redemption;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWallet(): Wallet
    {
        return $this->wallet;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function getRedemption(): ?Redemption
    {
        return $this->redemption;
    }

    public function getPointAmount(): string
    {
        return $this->pointAmount;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
