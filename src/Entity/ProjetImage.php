<?php

namespace App\Entity;

use App\Repository\ProjetImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjetImageRepository::class)]
class ProjetImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Nom du fichier stocké sur le disque (ex: 550e8400_e29b41d4.jpg)
     */
    #[ORM\Column(length: 255)]
    private ?string $nomFichier = null;

    /**
     * Indique si cette image est la couverture du projet.
     * Une seule image cover par projet est garantie par la logique métier du contrôleur.
     */
    #[ORM\Column]
    private bool $isCover = false;

    #[ORM\ManyToOne(targetEntity: Projet::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Projet $projet = null;

    // ── Getters / Setters ────────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomFichier(): ?string
    {
        return $this->nomFichier;
    }

    public function setNomFichier(string $nomFichier): static
    {
        $this->nomFichier = $nomFichier;
        return $this;
    }

    public function isCover(): bool
    {
        return $this->isCover;
    }

    public function setIsCover(bool $isCover): static
    {
        $this->isCover = $isCover;
        return $this;
    }

    public function getProjet(): ?Projet
    {
        return $this->projet;
    }

    public function setProjet(?Projet $projet): static
    {
        $this->projet = $projet;
        return $this;
    }
}