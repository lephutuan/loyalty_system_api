<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WalletRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

#[ORM\Entity(repositoryClass: WalletRepository::class)]
#[ORM\Table(name: 'wallets')]
class Wallet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'wallet', targetEntity: Member::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Member $member;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, options: ['default' => '0.00'])]
    private string $balance = '0.00';

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, Point>
     */
    #[ORM\OneToMany(mappedBy: 'wallet', targetEntity: Point::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $points;

    public function __construct(Member $member)
    {
        $this->member = $member;
        $this->updatedAt = new DateTimeImmutable();
        $this->points = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMember(): Member
    {
        return $this->member;
    }

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function credit(string $pointAmount): void
    {
        $this->balance = bcadd($this->balance, $pointAmount, 2);
        $this->updatedAt = new DateTimeImmutable();
    }

    public function debit(string $pointAmount): void
    {
        if ($this->canAfford($pointAmount) === false) {
            throw new InvalidArgumentException('Insufficient balance.');
        }

        $this->balance = bcsub($this->balance, $pointAmount, 2);
        $this->updatedAt = new DateTimeImmutable();
    }

    public function canAfford(string $pointAmount): bool
    {
        return bccomp($this->balance, $pointAmount, 2) >= 0;
    }

    public function addPoint(Point $point): void
    {
        if (!$this->points->contains($point)) {
            $this->points->add($point);
        }
    }
}
