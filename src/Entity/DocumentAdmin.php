<?php

namespace App\Entity;

use App\Repository\DocumentAdminRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentAdminRepository::class)]
class DocumentAdmin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $fichier = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $deposeLe = null;

    /**
     * L'utilisateur destinataire du document
     */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $destinataire = null;

    /**
     * L'admin qui a déposé le document
     */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $deposePar = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getFichier(): ?string
    {
        return $this->fichier;
    }

    public function setFichier(string $fichier): static
    {
        $this->fichier = $fichier;

        return $this;
    }

    public function getDeposeLe(): ?\DateTimeImmutable
    {
        return $this->deposeLe;
    }

    public function setDeposeLe(\DateTimeImmutable $deposeLe): static
    {
        $this->deposeLe = $deposeLe;

        return $this;
    }

    public function getDestinataire(): ?Utilisateur
    {
        return $this->destinataire;
    }

    public function setDestinataire(?Utilisateur $destinataire): static
    {
        $this->destinataire = $destinataire;

        return $this;
    }

    public function getDeposePar(): ?Utilisateur
    {
        return $this->deposePar;
    }

    public function setDeposePar(?Utilisateur $deposePar): static
    {
        $this->deposePar = $deposePar;

        return $this;
    }
}