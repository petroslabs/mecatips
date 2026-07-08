<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReportStatus;
use App\Repository\ReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
class Report
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tip::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Tip $tip;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $reporter;

    #[ORM\Column(type: Types::TEXT)]
    private string $reason;

    #[ORM\Column(length: 20, enumType: ReportStatus::class)]
    private ReportStatus $status = ReportStatus::Pending;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTip(): Tip
    {
        return $this->tip;
    }

    public function setTip(Tip $tip): static
    {
        $this->tip = $tip;

        return $this;
    }

    public function getReporter(): User
    {
        return $this->reporter;
    }

    public function setReporter(User $reporter): static
    {
        $this->reporter = $reporter;

        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getStatus(): ReportStatus
    {
        return $this->status;
    }

    public function setStatus(ReportStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
