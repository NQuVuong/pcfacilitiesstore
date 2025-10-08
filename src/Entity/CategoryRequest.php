<?php

namespace App\Entity;

use App\Repository\CategoryRequestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryRequestRepository::class)]
class CategoryRequest
{
    public const STATUS_PENDING  = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $requestedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\ManyToOne]
    private ?User $decidedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $v): self { $this->reason = $v; return $this; }
    public function getRequestedBy(): ?User { return $this->requestedBy; }
    public function setRequestedBy(User $u): self { $this->requestedBy = $u; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }
    public function getDecidedBy(): ?User { return $this->decidedBy; }
    public function setDecidedBy(?User $u): self { $this->decidedBy = $u; return $this; }
    public function getDecidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }
    public function setDecidedAt(?\DateTimeImmutable $t): self { $this->decidedAt = $t; return $this; }
}
