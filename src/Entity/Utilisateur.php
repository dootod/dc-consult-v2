<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
// ✅ FIX ANTI-ÉNUMÉRATION (OWASP A07) : le message de contrainte UniqueEntity ne doit pas
// révéler qu'un compte existe avec cet email. Ce message s'affiche lors de la soumission
// du formulaire d'inscription — un attaquant ne doit pas pouvoir l'exploiter pour
// déterminer si un email est déjà enregistré.
// Le message générique évite cette fuite tout en restant utile à l'utilisateur légitime.
#[UniqueEntity(fields: ['email'], message: 'Un problème est survenu lors de la création du compte. Veuillez vérifier vos informations ou vous connecter si vous avez déjà un compte.')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    // ── Tokens de réinitialisation de mot de passe ───────────────────────────
    // Le token brut est envoyé par email, seul son hash SHA-256 est stocké ici.
    // Expiration à 1 heure — usage unique (invalidé après utilisation).
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordChangeToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordChangeTokenExpiresAt = null;

    // ── Tokens de changement d'email (double confirmation) ──────────────────
    // Même principe : token brut dans l'email, hash SHA-256 en BDD.
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $pendingEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailChangeToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailChangeTokenExpiresAt = null;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'utilisateur', cascade: ['remove'])]
    private Collection $documents;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getPasswordChangeToken(): ?string
    {
        return $this->passwordChangeToken;
    }

    public function setPasswordChangeToken(?string $passwordChangeToken): static
    {
        $this->passwordChangeToken = $passwordChangeToken;

        return $this;
    }

    public function getPasswordChangeTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->passwordChangeTokenExpiresAt;
    }

    public function setPasswordChangeTokenExpiresAt(?\DateTimeImmutable $passwordChangeTokenExpiresAt): static
    {
        $this->passwordChangeTokenExpiresAt = $passwordChangeTokenExpiresAt;

        return $this;
    }

    public function getPendingEmail(): ?string
    {
        return $this->pendingEmail;
    }

    public function setPendingEmail(?string $pendingEmail): static
    {
        $this->pendingEmail = $pendingEmail;

        return $this;
    }

    public function getEmailChangeToken(): ?string
    {
        return $this->emailChangeToken;
    }

    public function setEmailChangeToken(?string $emailChangeToken): static
    {
        $this->emailChangeToken = $emailChangeToken;

        return $this;
    }

    public function getEmailChangeTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->emailChangeTokenExpiresAt;
    }

    public function setEmailChangeTokenExpiresAt(?\DateTimeImmutable $emailChangeTokenExpiresAt): static
    {
        $this->emailChangeTokenExpiresAt = $emailChangeTokenExpiresAt;

        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setUtilisateur($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getUtilisateur() === $this) {
                $document->setUtilisateur(null);
            }
        }

        return $this;
    }
}