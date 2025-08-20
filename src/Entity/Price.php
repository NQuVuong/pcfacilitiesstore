<?php

namespace App\Entity;

use App\Repository\PriceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PriceRepository::class)]
#[ORM\Table(name: 'price')]
class Price
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Giá nhập: điền lúc tạo sản phẩm lần đầu; về sau KHÔNG sửa
    #[ORM\Column(type: 'float')]
    private float $importPrice;

    // Giá xuất: admin có thể đặt/chỉnh sửa => mỗi lần đổi sẽ tạo 1 dòng mới
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $exportPrice = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne(inversedBy: 'prices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getImportPrice(): float 
    { 
        return $this->importPrice; 
    }
    public function setImportPrice(float $importPrice): self 
    { 
        $this->importPrice = $importPrice; 
        return $this; 
    }

    public function getExportPrice(): ?float 
    { 
        return $this->exportPrice; 
    }
    public function setExportPrice(?float $exportPrice): self 
    { 
        $this->exportPrice = $exportPrice; 
        return $this; 
    }

    public function getCreatedAt(): \DateTime 
    { 
        return $this->createdAt; 
    }
    public function setCreatedAt(\DateTime $createdAt): self 
    { 
        $this->createdAt = $createdAt; 
        return $this; 
    }

    public function getProduct(): ?Product 
    { 
        return $this->product; 
    }
    public function setProduct(?Product $product): self 
    { 
        $this->product = $product; 
        return $this; 
    }
}
