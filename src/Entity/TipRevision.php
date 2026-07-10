<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RevisionStatus;
use App\Repository\TipRevisionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TipRevisionRepository::class)]
class TipRevision
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tip::class, inversedBy: 'revisions')]
    #[ORM\JoinColumn(nullable: false)]
    private Tip $tip;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(type: 'revision_status', length: 20)]
    private RevisionStatus $status = RevisionStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $submittedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    /** @var Collection<int, CommitteeVote> */
    #[ORM\OneToMany(targetEntity: CommitteeVote::class, mappedBy: 'revision', orphanRemoval: true)]
    private Collection $votes;

    public function __construct()
    {
        $this->submittedAt = new \DateTimeImmutable();
        $this->votes = new ArrayCollection();
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getStatus(): RevisionStatus
    {
        return $this->status;
    }

    public function setStatus(RevisionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSubmittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;

        return $this;
    }

    /** @return Collection<int, CommitteeVote> */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(CommitteeVote $vote): static
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setRevision($this);
        }

        return $this;
    }
}
