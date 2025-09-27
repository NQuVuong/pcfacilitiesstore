<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
class OrderItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

    #[ORM\ManyToOne]
    private ?Product $product = null; // để null để không vỡ khi xóa product

    #[ORM\Column(length: 255)]
    private string $productName;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $unitPrice = '0.00';

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $lineTotal = '0.00';

    public function getId(): ?int { return $this->id; }
    public function getOrder(): ?Order { return $this->order; }
    public function setOrder(?Order $order): self { $this->order = $order; return $this; }
    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): self { $this->product = $product; return $this; }
    public function getProductName(): string { return $this->productName; }
    public function setProductName(string $name): self { $this->productName = $name; return $this; }
    public function getUnitPrice(): string { return $this->unitPrice; }
    public function setUnitPrice(string $price): self { $this->unitPrice = $price; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $q): self { $this->quantity = $q; return $this; }
    public function getLineTotal(): string { return $this->lineTotal; }
    public function setLineTotal(string $t): self { $this->lineTotal = $t; return $this; }
}
