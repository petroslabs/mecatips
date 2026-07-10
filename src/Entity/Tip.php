<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TipStatus;
use App\Enum\TipType;
use App\Repository\TipRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TipRepository::class)]
class Tip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tips')]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Category $category;

    #[ORM\ManyToOne(targetEntity: Vehicle::class, inversedBy: 'tips')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(type: 'tip_type', length: 20)]
    private TipType $type;

    #[ORM\Column(type: 'tip_status', length: 20)]
    private TipStatus $status = TipStatus::PENDING;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $publishedTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $publishedContent = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'tip_tag')]
    private Collection $tags;

    /** @var Collection<int, TipRevision> */
    #[ORM\OneToMany(targetEntity: TipRevision::class, mappedBy: 'tip', orphanRemoval: true)]
    private Collection $revisions;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->tags = new ArrayCollection();
        $this->revisions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;

        return $this;
    }

    public function getType(): TipType
    {
        return $this->type;
    }

    public function setType(TipType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): TipStatus
    {
        return $this->status;
    }

    public function setStatus(TipStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPublishedTitle(): ?string
    {
        return $this->publishedTitle;
    }

    public function setPublishedTitle(?string $publishedTitle): static
    {
        $this->publishedTitle = $publishedTitle;

        return $this;
    }

    public function getPublishedContent(): ?string
    {
        return $this->publishedContent;
    }

    public function setPublishedContent(?string $publishedContent): static
    {
        $this->publishedContent = $publishedContent;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /** @return Collection<int, TipRevision> */
    public function getRevisions(): Collection
    {
        return $this->revisions;
    }

    public function addRevision(TipRevision $revision): static
    {
        if (!$this->revisions->contains($revision)) {
            $this->revisions->add($revision);
            $revision->setTip($this);
        }

        return $this;
    }
}
