<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InsurancePolicyPropertyChecklistRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InsurancePolicyPropertyChecklistRepository::class)]
#[ORM\Table(name: 'insurance_policy_property_checklists')]
class InsurancePolicyPropertyChecklist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InsurancePolicy::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?InsurancePolicy $insurancePolicy = null;

    #[ORM\ManyToOne(targetEntity: PropertyChecklist::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?PropertyChecklist $propertyChecklist = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $value = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

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

    public function getPropertyChecklist(): ?PropertyChecklist
    {
        return $this->propertyChecklist;
    }

    public function setPropertyChecklist(?PropertyChecklist $propertyChecklist): static
    {
        $this->propertyChecklist = $propertyChecklist;

        return $this;
    }

    public function getValue(): ?bool
    {
        return $this->value;
    }

    public function setValue(bool $value): static
    {
        $this->value = $value;

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
}
