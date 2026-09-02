<?php

namespace App\Entity;

use App\Repository\RemiseRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une remise/avantage accordé à un client (ex: geste commercial, fidélité),
 * affiché dans son historique aux côtés de ses commandes. La date est
 * toujours celle du jour où la remise est enregistrée (non modifiable).
 */
#[ORM\Entity(repositoryClass: RemiseRepository::class)]
class Remise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'remises')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $montant = null;

    /**
     * Vrai uniquement pour une remise accordée depuis le bouton dédié de la
     * carte Fidélité — c'est ce qui permet de savoir si le palier de
     * fidélité actuel a déjà été honoré, sans compter les remises
     * ajoutées manuellement pour d'autres raisons (geste commercial, etc.).
     */
    #[ORM\Column]
    private bool $estFidelite = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $date = null;

    public function __construct()
    {
        $this->date = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
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

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function isEstFidelite(): bool
    {
        return $this->estFidelite;
    }

    public function setEstFidelite(bool $estFidelite): static
    {
        $this->estFidelite = $estFidelite;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }
}
