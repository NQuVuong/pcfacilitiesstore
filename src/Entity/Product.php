<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $created = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Brand $brand = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column]
    private ?int $Quantity = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Price::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $prices;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $slug = '';

    #[ORM\ManyToOne(targetEntity: Supplier::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Supplier $supplier = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $specs = [];

    // NEW: views
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $views = 0;

    // NEW: reviews
    /** @var Collection<int, ProductReview> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductReview::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $reviews;

    public function __construct()
    {
        $this->prices = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): static { $this->category = $category; return $this; }

    public function getBrand(): ?Brand { return $this->brand; }
    public function setBrand(?Brand $brand): static { $this->brand = $brand; return $this; }

    public function getCreated(): ?\DateTimeInterface { return $this->created; }
    public function setCreated(\DateTimeInterface $created): static { $this->created = $created; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }

    public function getQuantity(): ?int { return $this->Quantity; }
    public function setQuantity(int $Quantity): static { $this->Quantity = $Quantity; return $this; }

    /** @return Collection<int, Price> */
    public function getPrices(): Collection { return $this->prices; }

    public function addPrice(Price $price): self
    {
        if (!$this->prices->contains($price)) {
            $this->prices->add($price);
            $price->setProduct($this);
        }
        return $this;
    }

    public function removePrice(Price $price): self
    {
        if ($this->prices->removeElement($price)) {
            if ($price->getProduct() === $this) {
                $price->setProduct(null);
            }
        }
        return $this;
    }

    public function getCurrentExportPrice(): ?float
    {
        $latest = $this->prices->first();
        return $latest ? $latest->getExportPrice() : null;
    }

    public function getOriginalImportPrice(): ?float
    {
        $oldest = $this->prices->last();
        return $oldest ? $oldest->getImportPrice() : null;
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection { return $this->images; }

    public function addImage(ProductImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduct($this);
        }
        return $this;
    }

    public function removeImage(ProductImage $image): self
    {
        if ($this->images->removeElement($image)) {
            if ($image->getProduct() === $this) {
                $image->setProduct(null);
            }
        }
        return $this;
    }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getSupplier(): ?Supplier { return $this->supplier; }
    public function setSupplier(?Supplier $supplier): static { $this->supplier = $supplier; return $this; }

    public function getSpecs(): array { return $this->specs ?? []; }
    public function setSpecs(?array $specs): self { $this->specs = $specs; return $this; }

    // views
    public function getViews(): int { return $this->views; }
    public function setViews(int $views): self { $this->views = max(0, $views); return $this; }
    public function increaseViews(): self { $this->views++; return $this; }

    // ===== Reviews helpers =====

    /** @return Collection<int, ProductReview> */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(ProductReview $review): self
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setProduct($this);
        }
        return $this;
    }

    public function removeReview(ProductReview $review): self
    {
        if ($this->reviews->removeElement($review)) {
            if ($review->getProduct() === $this) {
                $review->setProduct(null);
            }
        }
        return $this;
    }

    public function getReviewCount(): int
    {
        return $this->reviews?->count() ?? 0;
    }

    public function getAverageRating(): float
    {
        $count = $this->getReviewCount();
        if ($count === 0) {
            return 0.0;
        }

        $sum = 0;
        foreach ($this->reviews as $r) {
            $sum += $r->getRating();
        }

        return round($sum / $count, 1);
    }
}
