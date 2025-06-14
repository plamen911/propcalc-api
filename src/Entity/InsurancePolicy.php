<?php

namespace App\Entity;

use App\Repository\InsurancePolicyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\WaterDistance;

#[ORM\Entity(repositoryClass: InsurancePolicyRepository::class)]
#[ORM\Table(name: 'insurance_policies')]
#[ORM\HasLifecycleCallbacks]
class InsurancePolicy
{
    public function __construct()
    {
        $this->insurancePolicyClauses = new ArrayCollection();
        $this->insurancePolicyPropertyChecklists = new ArrayCollection();
    }
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PersonRole::class)]
    #[ORM\JoinColumn(name: 'person_role_id', referencedColumnName: 'id')]
    private ?PersonRole $personRole = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $fullName = null;

    #[ORM\ManyToOne(targetEntity: IdNumberType::class)]
    #[ORM\JoinColumn(name: 'id_number_type_id', referencedColumnName: 'id')]
    private ?IdNumberType $idNumberType = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $idNumber = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $birthDate = null;

    #[ORM\ManyToOne(targetEntity: Nationality::class)]
    #[ORM\JoinColumn(name: 'insurer_nationality_id', referencedColumnName: 'id')]
    private ?Nationality $insurerNationality = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $gender = null;

    #[ORM\ManyToOne(targetEntity: Settlement::class)]
    #[ORM\JoinColumn(name: 'insurer_settlement_id', referencedColumnName: 'id')]
    private ?Settlement $insurerSettlement = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $permanentAddress = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $email = null;

    #[ORM\ManyToOne(targetEntity: Settlement::class)]
    #[ORM\JoinColumn(name: 'settlement_id', referencedColumnName: 'id')]
    private ?Settlement $settlement = null;

    #[ORM\ManyToOne(targetEntity: EstateType::class)]
    #[ORM\JoinColumn(name: 'estate_type_id', referencedColumnName: 'id')]
    private ?EstateType $estateType = null;

    #[ORM\ManyToOne(targetEntity: EstateType::class)]
    #[ORM\JoinColumn(name: 'estate_subtype_id', referencedColumnName: 'id')]
    private ?EstateType $estateSubtype = null;

    #[ORM\ManyToOne(targetEntity: WaterDistance::class)]
    #[ORM\JoinColumn(name: 'distance_to_water_id', referencedColumnName: 'id')]
    private ?WaterDistance $distanceToWater = null;

    #[ORM\Column(type: 'float')]
    private ?float $areaSqMeters = null;

    #[ORM\Column(type: 'float')]
    private ?float $subtotal = null;

    #[ORM\Column(type: 'float')]
    private ?float $discount = null;

    #[ORM\Column(type: 'float')]
    private ?float $subtotalTax = null;

    #[ORM\Column(type: 'float')]
    private ?float $total = null;

    #[ORM\ManyToOne(targetEntity: TariffPreset::class)]
    #[ORM\JoinColumn(name: 'tariff_preset_id', referencedColumnName: 'id')]
    private ?TariffPreset $tariffPreset = null;

    #[ORM\Column(name: 'tariff_preset_name', type: 'string', length: 255, nullable: true)]
    private ?string $tariffPresetName = null;

    #[ORM\Column(name: 'code', type: 'string', length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'insurancePolicy', targetEntity: InsurancePolicyClause::class, cascade: ['persist', 'remove'])]
    private Collection $insurancePolicyClauses;

    #[ORM\OneToMany(mappedBy: 'insurancePolicy', targetEntity: InsurancePolicyPropertyChecklist::class, cascade: ['persist', 'remove'])]
    private Collection $insurancePolicyPropertyChecklists;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettlement(): ?Settlement
    {
        return $this->settlement;
    }

    public function setSettlement(?Settlement $settlement): static
    {
        $this->settlement = $settlement;

        return $this;
    }

    public function getEstateType(): ?EstateType
    {
        return $this->estateType;
    }

    public function setEstateType(?EstateType $estateType): static
    {
        $this->estateType = $estateType;

        return $this;
    }

    public function getEstateSubtype(): ?EstateType
    {
        return $this->estateSubtype;
    }

    public function setEstateSubtype(?EstateType $estateSubtype): static
    {
        $this->estateSubtype = $estateSubtype;

        return $this;
    }

    public function getDistanceToWater(): ?WaterDistance
    {
        return $this->distanceToWater;
    }

    public function setDistanceToWater(?WaterDistance $distanceToWater): static
    {
        $this->distanceToWater = $distanceToWater;

        return $this;
    }

    public function getAreaSqMeters(): ?float
    {
        return $this->areaSqMeters;
    }

    public function setAreaSqMeters(float $areaSqMeters): static
    {
        $this->areaSqMeters = $areaSqMeters;

        return $this;
    }

    public function getSubtotal(): ?float
    {
        return $this->subtotal;
    }

    public function setSubtotal(float $subtotal): static
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(float $discount): static
    {
        $this->discount = $discount;

        return $this;
    }

    public function getSubtotalTax(): ?float
    {
        return $this->subtotalTax;
    }

    public function setSubtotalTax(float $subtotalTax): static
    {
        $this->subtotalTax = $subtotalTax;

        return $this;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(float $total): static
    {
        $this->total = $total;

        return $this;
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

    public function getTariffPresetName(): ?string
    {
        return $this->tariffPresetName;
    }

    public function setTariffPresetName(?string $tariffPresetName): static
    {
        $this->tariffPresetName = $tariffPresetName;

        return $this;
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

    public function getPersonRole(): ?PersonRole
    {
        return $this->personRole;
    }

    public function setPersonRole(?PersonRole $personRole): static
    {
        $this->personRole = $personRole;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getIdNumberType(): ?IdNumberType
    {
        return $this->idNumberType;
    }

    public function setIdNumberType(?IdNumberType $idNumberType): static
    {
        $this->idNumberType = $idNumberType;

        return $this;
    }

    public function getIdNumber(): ?string
    {
        return $this->idNumber;
    }

    public function setIdNumber(?string $idNumber): static
    {
        $this->idNumber = $idNumber;

        return $this;
    }

    public function getBirthDate(): ?\DateTimeInterface
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeInterface $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getInsurerNationality(): ?Nationality
    {
        return $this->insurerNationality;
    }

    public function setInsurerNationality(?Nationality $insurerNationality): static
    {
        $this->insurerNationality = $insurerNationality;

        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getInsurerSettlement(): ?Settlement
    {
        return $this->insurerSettlement;
    }

    public function setInsurerSettlement(?Settlement $insurerSettlement): static
    {
        $this->insurerSettlement = $insurerSettlement;

        return $this;
    }

    public function getPermanentAddress(): ?string
    {
        return $this->permanentAddress;
    }

    public function setPermanentAddress(?string $permanentAddress): static
    {
        $this->permanentAddress = $permanentAddress;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

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
            $clause->setInsurancePolicy($this);
        }

        return $this;
    }

    public function removeInsurancePolicyClause(InsurancePolicyClause $clause): static
    {
        if ($this->insurancePolicyClauses->removeElement($clause)) {
            // set the owning side to null (unless already changed)
            if ($clause->getInsurancePolicy() === $this) {
                $clause->setInsurancePolicy(null);
            }
        }

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
            $checklist->setInsurancePolicy($this);
        }

        return $this;
    }

    public function removeInsurancePolicyPropertyChecklist(InsurancePolicyPropertyChecklist $checklist): static
    {
        if ($this->insurancePolicyPropertyChecklists->removeElement($checklist)) {
            // set the owning side to null (unless already changed)
            if ($checklist->getInsurancePolicy() === $this) {
                $checklist->setInsurancePolicy(null);
            }
        }

        return $this;
    }
}
