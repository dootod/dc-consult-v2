<?php

namespace App\Entity;

use App\Repository\ProjetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjetRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Projet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'La localisation ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $localisation = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $date = null;

    /**
     * Taille du projet, ex : R+5 1100m²
     */
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'La taille ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $taille = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le maître d\'ouvrage ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $maitreOuvrage = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le maître d\'œuvre ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $maitreOeuvre = null;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    #[ORM\Column]
    private \DateTimeImmutable $modifieLe;

    /**
     * @var Collection<int, ProjetImage>
     */
    #[ORM\OneToMany(
        targetEntity: ProjetImage::class,
        mappedBy: 'projet',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['isCover' => 'DESC', 'id' => 'ASC'])]
    private Collection $images;

    public function __construct()
    {
        $this->images   = new ArrayCollection();
        $this->creeLe   = new \DateTimeImmutable();
        $this->modifieLe = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->modifieLe = new \DateTimeImmutable();
    }

    // ── Getters / Setters ────────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(?string $localisation): static
    {
        $this->localisation = $localisation;
        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getTaille(): ?string
    {
        return $this->taille;
    }

    public function setTaille(?string $taille): static
    {
        $this->taille = $taille;
        return $this;
    }

    public function getMaitreOuvrage(): ?string
    {
        return $this->maitreOuvrage;
    }

    public function setMaitreOuvrage(?string $maitreOuvrage): static
    {
        $this->maitreOuvrage = $maitreOuvrage;
        return $this;
    }

    public function getMaitreOeuvre(): ?string
    {
        return $this->maitreOeuvre;
    }

    public function setMaitreOeuvre(?string $maitreOeuvre): static
    {
        $this->maitreOeuvre = $maitreOeuvre;
        return $this;
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }

    public function getModifieLe(): \DateTimeImmutable
    {
        return $this->modifieLe;
    }

    /**
     * @return Collection<int, ProjetImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ProjetImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProjet($this);
        }
        return $this;
    }

    public function removeImage(ProjetImage $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getProjet() === $this) {
                $image->setProjet(null);
            }
        }
        return $this;
    }

    /**
     * Retourne l'image de couverture du projet, ou null s'il n'y en a pas.
     */
    public function getCoverImage(): ?ProjetImage
    {
        foreach ($this->images as $image) {
            if ($image->isCover()) {
                return $image;
            }
        }
        // Fallback : première image si aucune cover définie
        return $this->images->first() ?: null;
    }

    /**
     * Retourne les images du carousel (toutes sauf la cover).
     *
     * @return Collection<int, ProjetImage>
     */
    public function getCarouselImages(): Collection
    {
        return $this->images->filter(fn(ProjetImage $img) => !$img->isCover());
    }
}
