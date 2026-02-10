<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PromotionalCodeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PromotionalCodeRepository::class)]
#[ORM\Table(name: 'promotional_codes')]
class PromotionalCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Code cannot be blank')]
    #[Assert\Length(max: 50, maxMessage: 'Code cannot be longer than {{ limit }} characters')]
    private ?string $code = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Description cannot be blank')]
    private ?string $description = null;

    #[ORM\Column(type: 'float')]
    #[Assert\NotBlank(message: 'Discount percentage cannot be blank')]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'Discount percentage must be between {{ min }}% and {{ max }}%')]
    private ?float $discountPercentage = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $validFrom = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $validTo = null;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $usageLimit = null;

    #[ORM\Column(type: 'integer')]
    private int $usageCount = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDiscountPercentage(): ?float
    {
        return $this->discountPercentage;
    }

    public function setDiscountPercentage(float $discountPercentage): static
    {
        $this->discountPercentage = $discountPercentage;

        return $this;
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeInterface $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidTo(): ?\DateTimeInterface
    {
        return $this->validTo;
    }

    public function setValidTo(?\DateTimeInterface $validTo): static
    {
        $this->validTo = $validTo;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getUsageLimit(): ?int
    {
        return $this->usageLimit;
    }

    public function setUsageLimit(?int $usageLimit): static
    {
        $this->usageLimit = $usageLimit;

        return $this;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): static
    {
        $this->usageCount = $usageCount;

        return $this;
    }

    public function incrementUsageCount(): static
    {
        $this->usageCount++;

        return $this;
    }

    public function isValid(): bool
    {
        $now = new \DateTime();

        // Check if the code is active
        if (!$this->active) {
            return false;
        }

        // Check if the code has reached its usage limit
        if ($this->usageLimit !== null && $this->usageCount >= $this->usageLimit) {
            return false;
        }

        // Check if the code is within its validity period
        if ($this->validFrom !== null && $now < $this->validFrom) {
            return false;
        }

        if ($this->validTo !== null && $now > $this->validTo) {
            return false;
        }

        return true;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function toArray(): array
    {
        $userData = null;
        if ($this->user) {
            $userData = [
                'id' => $this->user->getId(),
                'email' => $this->user->getEmail(),
                'fullName' => $this->user->getFullName(),
            ];
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'discountPercentage' => $this->discountPercentage,
            'validFrom' => $this->validFrom?->format('Y-m-d H:i:s'),
            'validTo' => $this->validTo?->format('Y-m-d H:i:s'),
            'active' => $this->active,
            'usageLimit' => $this->usageLimit,
            'usageCount' => $this->usageCount,
            'isValid' => $this->isValid(),
            'user' => $userData,
        ];
    }
}
