<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InsuranceClauseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InsuranceClauseRepository::class)]
#[ORM\Table(name: 'insurance_clauses')]
class InsuranceClause
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: 'tariff_number', type: 'float')]
    private ?float $tariffNumber = null;

    #[ORM\Column(name: 'has_tariff_number', type: 'boolean')]
    private ?bool $hasTariffNumber = null;

    #[ORM\Column(name: 'tariff_amount', type: 'float')]
    private ?float $tariffAmount = null;

    #[ORM\Column(name: 'tariff_amount_coverage', type: 'float')]
    private ?float $tariffAmountCoverage = null;

    #[ORM\Column(name: 'allow_custom_amount', type: 'boolean')]
    private bool $allowCustomAmount = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'integer')]
    private ?int $position = null;

    #[ORM\Column(name: 'min_value', type: 'integer', nullable: true)]
    private ?int $minValue = null;

    #[ORM\Column(name: 'max_value', type: 'integer', nullable: true)]
    private ?int $maxValue = null;

    #[ORM\Column(name: 'step_value', type: 'integer', nullable: true)]
    private ?int $stepValue = null;

    #[ORM\OneToMany(targetEntity: InsurancePolicyClause::class, mappedBy: 'insuranceClause')]
    private Collection $insurancePolicyClauses;

    #[ORM\OneToMany(targetEntity: TariffPresetClause::class, mappedBy: 'insuranceClause')]
    private Collection $tariffPresetClauses;

    public function __construct()
    {
        $this->insurancePolicyClauses = new ArrayCollection();
        $this->tariffPresetClauses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getHasTariffNumber(): ?bool
    {
        return $this->hasTariffNumber;
    }

    public function setHasTariffNumber(bool $hasTariffNumber): static
    {
        $this->hasTariffNumber = $hasTariffNumber;

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

    public function getTariffAmountCoverage(): ?float
    {
        return $this->tariffAmountCoverage;
    }

    public function setTariffAmountCoverage(float $tariffAmountCoverage): static
    {
        $this->tariffAmountCoverage = $tariffAmountCoverage;

        return $this;
    }

    public function getAllowCustomAmount(): bool
    {
        return $this->allowCustomAmount;
    }

    public function setAllowCustomAmount(bool $allowCustomAmount): static
    {
        $this->allowCustomAmount = $allowCustomAmount;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getMinValue(): ?int
    {
        return $this->minValue;
    }

    public function setMinValue(?int $minValue): static
    {
        $this->minValue = $minValue;

        return $this;
    }

    public function getMaxValue(): ?int
    {
        return $this->maxValue;
    }

    public function setMaxValue(?int $maxValue): static
    {
        $this->maxValue = $maxValue;

        return $this;
    }

    public function getStepValue(): ?int
    {
        return $this->stepValue;
    }

    public function setStepValue(?int $stepValue): static
    {
        $this->stepValue = $stepValue;

        return $this;
    }

    /**
     * @return Collection<int, InsurancePolicyClause>
     */
    public function getInsurancePolicyClauses(): Collection
    {
        return $this->insurancePolicyClauses;
    }

    public function addInsurancePolicyClause(InsurancePolicyClause $clause): static
    {
        if (!$this->insurancePolicyClauses->contains($clause)) {
            $this->insurancePolicyClauses->add($clause);
            $clause->setInsuranceClause($this);
        }

        return $this;
    }

    public function removeInsurancePolicyClause(InsurancePolicyClause $clause): static
    {
        if ($this->insurancePolicyClauses->removeElement($clause)) {
            // set the owning side to null (unless already changed)
            if ($clause->getInsuranceClause() === $this) {
                $clause->setInsuranceClause(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TariffPresetClause>
     */
    public function getTariffPresetClauses(): Collection
    {
        return $this->tariffPresetClauses;
    }

    public function addTariffPresetClause(TariffPresetClause $clause): static
    {
        if (!$this->tariffPresetClauses->contains($clause)) {
            $this->tariffPresetClauses->add($clause);
            $clause->setInsuranceClause($this);
        }

        return $this;
    }

    public function removeTariffPresetClause(TariffPresetClause $clause): static
    {
        if ($this->tariffPresetClauses->removeElement($clause)) {
            // set the owning side to null (unless already changed)
            if ($clause->getInsuranceClause() === $this) {
                $clause->setInsuranceClause(null);
            }
        }

        return $this;
    }
}
