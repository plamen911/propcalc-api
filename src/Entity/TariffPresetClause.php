<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TariffPresetClauseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TariffPresetClauseRepository::class)]
#[ORM\Table(name: 'tariff_preset_clauses')]
class TariffPresetClause
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TariffPreset::class)]
    #[ORM\JoinColumn(name: 'tariff_preset_id', referencedColumnName: 'id')]
    private ?TariffPreset $tariffPreset = null;

    #[ORM\ManyToOne(targetEntity: InsuranceClause::class)]
    #[ORM\JoinColumn(name: 'insurance_clause_id', referencedColumnName: 'id')]
    private ?InsuranceClause $insuranceClause = null;

    #[ORM\Column(type: 'float')]
    private ?float $tariffAmount = null;

    #[ORM\Column(type: 'integer')]
    private ?int $position = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTariffPreset(): ?TariffPreset
    {
        return $this->tariffPreset;
    }

    public function setTariffPreset(?TariffPreset $tariffPreset): static
    {
        $this->tariffPreset = $tariffPreset;

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
}
