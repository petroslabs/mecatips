<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UsefulVoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UsefulVoteRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_useful_vote_tip_user', fields: ['tip', 'user'])]
class UsefulVote
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
    private User $user;

    #[ORM\Column]
    private bool $useful;

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

    public function getTip(): Tip
    {
        return $this->tip;
    }

    public function setTip(Tip $tip): static
    {
        $this->tip = $tip;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function isUseful(): bool
    {
        return $this->useful;
    }

    public function setUseful(bool $useful): static
    {
        $this->useful = $useful;

        return $this;
    }

    public function getVotedAt(): \DateTimeImmutable
    {
        return $this->votedAt;
    }
}
