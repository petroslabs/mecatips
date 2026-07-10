<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\VoteDecision;
use App\Repository\CommitteeVoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommitteeVoteRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_vote_revision_member', fields: ['revision', 'member'])]
class CommitteeVote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TipRevision::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false)]
    private TipRevision $revision;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $member;

    #[ORM\Column(type: 'vote_decision', length: 10)]
    private VoteDecision $decision;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $votedAt;

    public function __construct()
    {
        $this->votedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRevision(): TipRevision
    {
        return $this->revision;
    }

    public function setRevision(TipRevision $revision): static
    {
        $this->revision = $revision;

        return $this;
    }

    public function getMember(): User
    {
        return $this->member;
    }

    public function setMember(User $member): static
    {
        $this->member = $member;

        return $this;
    }

    public function getDecision(): VoteDecision
    {
        return $this->decision;
    }

    public function setDecision(VoteDecision $decision): static
    {
        $this->decision = $decision;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getVotedAt(): \DateTimeImmutable
    {
        return $this->votedAt;
    }
}
