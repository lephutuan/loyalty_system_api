<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RedemptionStatus;
use App\Repository\RedemptionRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RedemptionRepository::class)]
#[ORM\Table(name: 'redemptions')]
class Redemption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Member::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Member $member;

    #[ORM\ManyToOne(targetEntity: Gift::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Gift $gift;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $pointsUsed;

    #[ORM\Column(length: 32, enumType: RedemptionStatus::class)]
    private RedemptionStatus $status;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(Member $member, Gift $gift, string $pointsUsed, RedemptionStatus $status)
    {
        $this->member = $member;
        $this->gift = $gift;
        $this->pointsUsed = $pointsUsed;
        $this->status = $status;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMember(): Member
    {
        return $this->member;
    }

    public function getGift(): Gift
    {
        return $this->gift;
    }

    public function getPointsUsed(): string
    {
        return $this->pointsUsed;
    }

    public function getStatus(): RedemptionStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
