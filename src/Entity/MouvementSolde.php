<?php

namespace App\Entity;

use App\Repository\MouvementSoldeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un mouvement sur le solde client (positif = dépôt, négatif = utilisation
 * du solde pour payer une commande), qui apparaît dans l'historique de la
 * fiche. Le solde du client est signé : positif = réserve, négatif = dette.
 */
#[ORM\Entity(repositoryClass: MouvementSoldeRepository::class)]
class MouvementSolde
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'mouvementsSolde')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    /**
     * Signé : positif pour un dépôt, négatif pour un paiement effectué via
     * le solde.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $montant = null;

    #[ORM\Column(length: 255)]
    private ?string $motif = null;

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

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }
}
