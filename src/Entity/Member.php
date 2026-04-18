<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MemberRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MemberRepository::class)]
#[ORM\Table(name: 'members')]
#[ORM\HasLifecycleCallbacks]
class Member
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $fullname = '';

    #[ORM\Column(length: 255, unique: true)]
    private string $email = '';

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\OneToOne(mappedBy: 'member', targetEntity: Wallet::class, cascade: ['persist', 'remove'])]
    private ?Wallet $wallet = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public static function register(string $fullname, string $email): self
    {
        $member = new self();
        $member->fullname = $fullname;
        $member->email = $email;
        $member->wallet = new Wallet($member);

        return $member;
    }

    #[ORM\PrePersist]
    public function ensureWalletExists(): void
    {
        if ($this->wallet === null) {
            $this->wallet = new Wallet($this);
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullname(): string
    {
        return $this->fullname;
    }

    public function setFullname(string $fullname): self
    {
        $this->fullname = $fullname;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getWallet(): Wallet
    {
        if ($this->wallet === null) {
            $this->wallet = new Wallet($this);
        }

        return $this->wallet;
    }

    public function setWallet(Wallet $wallet): void
    {
        $this->wallet = $wallet;
    }
}
