<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_CONFIRMEE = 'confirmee';
    public const STATUT_ANNULEE = 'annulee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Pas de compte client : on demande juste les coordonnées à chaque commande.
    #[ORM\Column(length: 255)]
    private ?string $nomClient = null;

    #[ORM\Column(length: 255)]
    private ?string $emailClient = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephoneClient = null;

    #[ORM\ManyToOne(targetEntity: JourRetrait::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?JourRetrait $jourRetrait = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateCommande = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_ATTENTE;

    /**
     * @var Collection<int, LigneCommande>
     */
    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: LigneCommande::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->dateCommande = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomClient(): ?string
    {
        return $this->nomClient;
    }

    public function setNomClient(string $nomClient): static
    {
        $this->nomClient = $nomClient;

        return $this;
    }

    public function getEmailClient(): ?string
    {
        return $this->emailClient;
    }

    public function setEmailClient(string $emailClient): static
    {
        $this->emailClient = $emailClient;

        return $this;
    }

    public function getTelephoneClient(): ?string
    {
        return $this->telephoneClient;
    }

    public function setTelephoneClient(?string $telephoneClient): static
    {
        $this->telephoneClient = $telephoneClient;

        return $this;
    }

    public function getJourRetrait(): ?JourRetrait
    {
        return $this->jourRetrait;
    }

    public function setJourRetrait(?JourRetrait $jourRetrait): static
    {
        $this->jourRetrait = $jourRetrait;

        return $this;
    }

    public function getDateCommande(): ?\DateTimeImmutable
    {
        return $this->dateCommande;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    /**
     * @return Collection<int, LigneCommande>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function ajouterLigne(LigneCommande $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setCommande($this);
        }

        return $this;
    }

    /**
     * Total de la commande, calculé à partir des lignes (prix figé au moment
     * de la commande, donc fiable même si le prix du produit change ensuite).
     */
    public function getTotal(): string
    {
        $total = '0.00';
        foreach ($this->lignes as $ligne) {
            $total = bcadd($total, $ligne->getSousTotal(), 2);
        }

        return $total;
    }
}
