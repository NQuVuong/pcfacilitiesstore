<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

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

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // mọi user luôn có ROLE_USER
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    /** helper */
    public function isAdmin(): bool    { return in_array(self::ROLE_ADMIN, $this->getRoles(), true); }
    public function isStaff(): bool    { return in_array(self::ROLE_STAFF, $this->getRoles(), true) || $this->isAdmin(); }
    public function isCustomer(): bool { return in_array(self::ROLE_CUSTOMER, $this->getRoles(), true); }

    /** tránh rò rỉ hash trong session (Symfony 7.3) */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', (string)$this->password);
        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void {}
}
