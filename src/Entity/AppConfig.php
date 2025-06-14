<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AppConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppConfigRepository::class)]
#[ORM\Table(name: 'app_configs')]
#[ORM\Index(name: 'idx_app_config_name', columns: ['name'])]
class AppConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $value = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nameBg = null;

    #[ORM\Column(name: 'is_editable', type: 'boolean')]
    private ?bool $isEditable = true;

    #[ORM\Column(name: 'position', type: 'integer')]
    private ?int $position = 0;

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

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getNameBg(): ?string
    {
        return $this->nameBg;
    }

    public function setNameBg(?string $nameBg): static
    {
        $this->nameBg = $nameBg;

        return $this;
    }

    public function isEditable(): ?bool
    {
        return $this->isEditable;
    }

    public function setIsEditable(bool $isEditable): static
    {
        $this->isEditable = $isEditable;

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
