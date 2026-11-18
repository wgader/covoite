<?php

namespace App\Entity;

use App\Repository\VehicleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\LuggageSize;
use App\Enum\VehicleType;

#[ORM\Entity(repositoryClass: VehicleRepository::class)]
class Vehicle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $brand = null;

    #[ORM\Column(length: 100)]
    private ?string $model = null;

    #[ORM\Column(length: 50)]
    private ?string $color = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $seats = null;

#[ORM\Column(enumType: LuggageSize::class)]
    private LuggageSize $trunkSize = LuggageSize::Medium;

    #[ORM\Column]
    private bool $isElectric = false;

    #[ORM\Column]
    private bool $hasAc = false;

    #[ORM\Column]
    private bool $hasUsb = false;

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\ManyToOne(inversedBy: 'vehicles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getSeats(): ?int
    {
        return $this->seats;
    }

    public function setSeats(int $seats): static
    {
        $this->seats = $seats;

        return $this;
    }

    public function getTrunkSize(): LuggageSize
    {
        return $this->trunkSize;
    }

    public function setTrunkSize(LuggageSize $trunkSize): static
    {
        $this->trunkSize = $trunkSize;

        return $this;
    }

    public function isElectric(): ?bool
    {
        return $this->isElectric;
    }

    public function setIsElectric(bool $isElectric): static
    {
        $this->isElectric = $isElectric;

        return $this;
    }

    public function hasAc(): ?bool
    {
        return $this->hasAc;
    }

    public function setHasAc(bool $hasAc): static
    {
        $this->hasAc = $hasAc;

        return $this;
    }

    public function hasUsb(): ?bool
    {
        return $this->hasUsb;
    }

    public function setHasUsb(bool $hasUsb): static
    {
        $this->hasUsb = $hasUsb;

        return $this;
    }

    public function isDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }
}
