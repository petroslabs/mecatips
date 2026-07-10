<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\VehicleStatus;
use App\Repository\VehicleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehicleRepository::class)]
class Vehicle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Nullable : le formulaire de soumission de tip n'expose qu'un champ de
    // recherche libre sur `label` (pas de cascade marque/modèle/moteur — voir
    // ROADMAP.md). Un véhicule proposé depuis ce flux n'a donc pas forcément
    // make/model renseignés ; c'est à l'outil de dédoublonnage admin (à
    // venir) de les compléter.
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $make = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $engine = null;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(type: 'vehicle_status', length: 20)]
    private VehicleStatus $status = VehicleStatus::PENDING;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'proposedVehicles')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $proposedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Tip> */
    #[ORM\OneToMany(targetEntity: Tip::class, mappedBy: 'vehicle')]
    private Collection $tips;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->tips = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMake(): ?string
    {
        return $this->make;
    }

    public function setMake(?string $make): static
    {
        $this->make = $make;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getEngine(): ?string
    {
        return $this->engine;
    }

    public function setEngine(?string $engine): static
    {
        $this->engine = $engine;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getStatus(): VehicleStatus
    {
        return $this->status;
    }

    public function setStatus(VehicleStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getProposedBy(): ?User
    {
        return $this->proposedBy;
    }

    public function setProposedBy(?User $proposedBy): static
    {
        $this->proposedBy = $proposedBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Tip> */
    public function getTips(): Collection
    {
        return $this->tips;
    }
}
