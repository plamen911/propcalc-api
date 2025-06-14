<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PropertyChecklistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PropertyChecklistRepository::class)]
#[ORM\Table(name: 'property_checklists')]
class PropertyChecklist
{
    public function __construct()
    {
        $this->insurancePolicyPropertyChecklists = new ArrayCollection();
    }
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'integer')]
    private ?int $position = null;

    #[ORM\OneToMany(mappedBy: 'propertyChecklist', targetEntity: InsurancePolicyPropertyChecklist::class)]
    private Collection $insurancePolicyPropertyChecklists;

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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return Collection<int, InsurancePolicyPropertyChecklist>
     */
    public function getInsurancePolicyPropertyChecklists(): Collection
    {
        return $this->insurancePolicyPropertyChecklists;
    }

    public function addInsurancePolicyPropertyChecklist(InsurancePolicyPropertyChecklist $checklist): static
    {
        if (!$this->insurancePolicyPropertyChecklists->contains($checklist)) {
            $this->insurancePolicyPropertyChecklists->add($checklist);
            $checklist->setPropertyChecklist($this);
        }

        return $this;
    }

    public function removeInsurancePolicyPropertyChecklist(InsurancePolicyPropertyChecklist $checklist): static
    {
        if ($this->insurancePolicyPropertyChecklists->removeElement($checklist)) {
            // set the owning side to null (unless already changed)
            if ($checklist->getPropertyChecklist() === $this) {
                $checklist->setPropertyChecklist(null);
            }
        }

        return $this;
    }
}
