<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TariffPresetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TariffPresetRepository::class)]
#[ORM\Table(name: 'tariff_presets')]
class TariffPreset
{
    public function __construct()
    {
        $this->tariffPresetClauses = new ArrayCollection();
    }
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $active = null;

    #[ORM\Column(type: 'integer')]
    private ?int $position = null;

    #[ORM\OneToMany(mappedBy: 'tariffPreset', targetEntity: TariffPresetClause::class, cascade: ['persist', 'remove'])]
    private Collection $tariffPresetClauses;

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

    public function isActive(): ?bool
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
            $clause->setTariffPreset($this);
        }

        return $this;
    }

    public function removeTariffPresetClause(TariffPresetClause $clause): static
    {
        if ($this->tariffPresetClauses->removeElement($clause)) {
            // set the owning side to null (unless already changed)
            if ($clause->getTariffPreset() === $this) {
                $clause->setTariffPreset(null);
            }
        }

        return $this;
    }
}
