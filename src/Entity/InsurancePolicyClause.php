<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InsurancePolicyClauseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InsurancePolicyClauseRepository::class)]
#[ORM\Table(name: 'insurance_policy_clauses')]
#[ORM\HasLifecycleCallbacks]
class InsurancePolicyClause
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InsurancePolicy::class)]
    #[ORM\JoinColumn(name: 'insurance_policy_id', referencedColumnName: 'id', nullable: false, onDelete: "CASCADE")]
    private ?InsurancePolicy $insurancePolicy = null;

    #[ORM\ManyToOne(targetEntity: InsuranceClause::class)]
    #[ORM\JoinColumn(name: 'insurance_clause_id', referencedColumnName: 'id', nullable: true, onDelete: "SET NULL")]
    private ?InsuranceClause $insuranceClause = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: 'tariff_number', type: 'float')]
    private ?float $tariffNumber = null;

    #[ORM\Column(name: 'tariff_amount', type: 'float')]
    private ?float $tariffAmount = null;

    #[ORM\Column(type: 'integer')]
    private ?int $position = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInsurancePolicy(): ?InsurancePolicy
    {
        return $this->insurancePolicy;
    }

    public function setInsurancePolicy(?InsurancePolicy $insurancePolicy): static
    {
        $this->insurancePolicy = $insurancePolicy;

        return $this;
    }

    public function getInsuranceClause(): ?InsuranceClause
    {
        return $this->insuranceClause;
    }

    public function setInsuranceClause(?InsuranceClause $insuranceClause): static
    {
        $this->insuranceClause = $insuranceClause;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getTariffNumber(): ?float
    {
        return $this->tariffNumber;
    }

    public function setTariffNumber(float $tariffNumber): static
    {
        $this->tariffNumber = $tariffNumber;

        return $this;
    }

    public function getTariffAmount(): ?float
    {
        return $this->tariffAmount;
    }

    public function setTariffAmount(float $tariffAmount): static
    {
        $this->tariffAmount = $tariffAmount;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
