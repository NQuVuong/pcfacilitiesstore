<?php
namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Order;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_ADMIN    = 'ROLE_ADMIN';
    public const ROLE_STAFF    = 'ROLE_STAFF';
    public const ROLE_CUSTOMER = 'ROLE_CUSTOMER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $fullName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $birthday = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $gender = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    // ----- Email / OTP verify -----
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $verifyToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verifyExpiresAt = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $otpCode = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $otpExpiresAt = null;

    /** @var Collection<int, Order> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Order::class, orphanRemoval: false)]
    private Collection $orders;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
    }

    // ===== basic =====
    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    public function eraseCredentials(): void {}

    // ===== role helper =====
    public function isAdmin(): bool    { return in_array(self::ROLE_ADMIN, $this->getRoles(), true); }
    public function isStaff(): bool    { return in_array(self::ROLE_STAFF, $this->getRoles(), true) || $this->isAdmin(); }
    public function isCustomer(): bool { return in_array(self::ROLE_CUSTOMER, $this->getRoles(), true); }

    // ===== profile =====
    public function getFullName(): ?string { return $this->fullName; }
    public function setFullName(?string $fullName): static { $this->fullName = $fullName; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }

    public function getBirthday(): ?\DateTimeInterface { return $this->birthday; }
    public function setBirthday(?\DateTimeInterface $birthday): static { $this->birthday = $birthday; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): static { $this->address = $address; return $this; }

    public function getGender(): ?string { return $this->gender; }
    public function setGender(?string $gender): static { $this->gender = $gender; return $this; }

    public function getAvatar(): ?string { return $this->avatar; }
    public function setAvatar(?string $avatar): static { $this->avatar = $avatar; return $this; }

    // ===== verify flag & link =====
    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $v): static { $this->isVerified = $v; return $this; }

    public function getVerifyToken(): ?string { return $this->verifyToken; }
    public function setVerifyToken(?string $t): static { $this->verifyToken = $t; return $this; }

    public function getVerifyExpiresAt(): ?\DateTimeImmutable { return $this->verifyExpiresAt; }
    public function setVerifyExpiresAt(?\DateTimeImmutable $d): static { $this->verifyExpiresAt = $d; return $this; }

    // ===== OTP =====
    public function getOtpCode(): ?string { return $this->otpCode; }
    public function setOtpCode(?string $code): static { $this->otpCode = $code; return $this; }

    public function getOtpExpiresAt(): ?\DateTimeImmutable { return $this->otpExpiresAt; }
    public function setOtpExpiresAt(?\DateTimeImmutable $d): static { $this->otpExpiresAt = $d; return $this; }

    public function clearOtp(): static
    {
        $this->otpCode = null;
        $this->otpExpiresAt = null;
        return $this;
    }

    // ===== orders relation =====
    /** @return Collection<int, Order> */
    public function getOrders(): Collection { return $this->orders; }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setUser($this);
        }
        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            if ($order->getUser() === $this) {
                $order->setUser(null);
            }
        }
        return $this;
    }

    // ===== serialize guard =====
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', (string) $this->password);
        return $data;
    }
}
