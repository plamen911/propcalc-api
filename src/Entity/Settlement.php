<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SettlementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettlementRepository::class)]
#[ORM\Table(name: 'settlements')]
#[ORM\UniqueConstraint(name: 'unique_settlement', columns: ['name', 'post_code'], options: ['lengths' => [191, null]])]
class Settlement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $postCode = null;

    #[ORM\ManyToOne(targetEntity: Region::class)]
    #[ORM\JoinColumn(name: 'region_id', referencedColumnName: 'id', nullable: false)]
    private ?Region $region = null;

    #[ORM\ManyToOne(targetEntity: Municipality::class)]
    #[ORM\JoinColumn(name: 'municipality_id', referencedColumnName: 'id', nullable: false)]
    private ?Municipality $municipality = null;

    #[ORM\ManyToOne(targetEntity: Type::class)]
    #[ORM\JoinColumn(name: 'type_id', referencedColumnName: 'id', nullable: false)]
    private ?Type $type = null;

    #[ORM\ManyToOne(targetEntity: EarthquakeZone::class)]
    #[ORM\JoinColumn(name: 'earthquake_zone_id', referencedColumnName: 'id', nullable: false)]
    private ?EarthquakeZone $earthquakeZone = null;

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

    public function getPostCode(): ?string
    {
        return $this->postCode;
    }

    public function setPostCode(string $postCode): static
    {
        $this->postCode = $postCode;

        return $this;
    }

    public function getRegion(): ?Region
    {
        return $this->region;
    }

    public function setRegion(?Region $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getMunicipality(): ?Municipality
    {
        return $this->municipality;
    }

    public function setMunicipality(?Municipality $municipality): static
    {
        $this->municipality = $municipality;

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getEarthquakeZone(): ?EarthquakeZone
    {
        return $this->earthquakeZone;
    }

    public function setEarthquakeZone(?EarthquakeZone $earthquakeZone): static
    {
        $this->earthquakeZone = $earthquakeZone;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->getType()->getName().' '. $this->getName().' (' .'Общ. '.$this->getMunicipality()->getName()
            .', Обл. '.$this->getRegion()->getName() .')';
    }
}
