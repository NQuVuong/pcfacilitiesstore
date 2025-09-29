<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 32)]
    private string $status = 'NEW';

    // Lưu tổng tiền (demo: decimal). Sản xuất nên cân nhắc integer (cents).
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $total = '0.00';

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customerName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customerEmail = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $customerPhone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $shippingAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    // COD, BANK, MOMO...
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $paymentMethod = null;

    // Thời điểm thanh toán thành công (IPN/return xác nhận)
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    // Mã giao dịch do cổng thanh toán trả về (vd: MoMo transId). Dùng cho tra soát/idempotency.
    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $paymentTxnId = null;

    // Hạn dùng phiên thanh toán (ví dụ: +1 hour kể từ lúc tạo yêu cầu)
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $dt): self { $this->createdAt = $dt; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getTotal(): string { return $this->total; }
    public function setTotal(string $total): self { $this->total = $total; return $this; }

    public function getCustomerName(): ?string { return $this->customerName; }
    public function setCustomerName(?string $v): self { $this->customerName = $v; return $this; }

    public function getCustomerEmail(): ?string { return $this->customerEmail; }
    public function setCustomerEmail(?string $v): self { $this->customerEmail = $v; return $this; }

    public function getCustomerPhone(): ?string { return $this->customerPhone; }
    public function setCustomerPhone(?string $v): self { $this->customerPhone = $v; return $this; }

    public function getShippingAddress(): ?string { return $this->shippingAddress; }
    public function setShippingAddress(?string $v): self { $this->shippingAddress = $v; return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $v): self { $this->note = $v; return $this; }

    public function getPaymentMethod(): ?string { return $this->paymentMethod; }
    public function setPaymentMethod(?string $v): self { $this->paymentMethod = $v; return $this; }

    /** @return Collection<int, OrderItem> */
    public function getItems(): Collection { return $this->items; }

    public function addItem(OrderItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }
        return $this;
    }

    public function removeItem(OrderItem $item): self
    {
        if ($this->items->removeElement($item) && $item->getOrder() === $this) {
            $item->setOrder(null);
        }
        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable { return $this->paidAt; }
    public function setPaidAt(?\DateTimeImmutable $dt): self { $this->paidAt = $dt; return $this; }

    public function getPaymentTxnId(): ?string { return $this->paymentTxnId; }
    public function setPaymentTxnId(?string $id): self { $this->paymentTxnId = $id; return $this; }

    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $dt): self { $this->expiresAt = $dt; return $this; }

    /** Đơn đã quá hạn hay chưa */
    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= new \DateTimeImmutable();
    }
}
