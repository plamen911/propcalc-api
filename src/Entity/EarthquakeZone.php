<?php

namespace App\Entity;

use App\Repository\EarthquakeZoneRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EarthquakeZoneRepository::class)]
#[ORM\Table(name: 'earthquake_zones')]
#[ORM\UniqueConstraint(name: 'UNIQ_859F8B335E237E06', columns: ['name'], options: ['lengths' => [191]])]
class EarthquakeZone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'float')]
    private ?float $tariff_number = null;

    #[ORM\Column(type: 'integer')]
    private ?int $position = null;

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
        return $this->tariff_number;
    }

    public function setTariffNumber(float $tariff_number): static
    {
        $this->tariff_number = $tariff_number;

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
