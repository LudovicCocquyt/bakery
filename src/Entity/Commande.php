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

    // Coordonnées telles que saisies au moment de CETTE commande (conservées
    // même si la fiche client est modifiée plus tard).
    #[ORM\Column(length: 255)]
    private ?string $nomClient = null;

    #[ORM\Column(length: 255)]
    private ?string $emailClient = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephoneClient = null;

    /**
     * Fiche client à laquelle cette commande est rattachée (retrouvée par
     * email ou téléphone au moment de la commande, ou créée si nouvelle).
     */
    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Client $client = null;

    /**
     * Montant réellement payé, renseigné par le boulanger au moment où le
     * pain est remis et payé — jamais calculé automatiquement. Null tant que
     * la commande n'a pas encore été payée.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $montantPaye = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $datePaiement = null;

    /**
     * Mode choisi par le boulanger au règlement : "especes", "solde"
     * (utilise le solde positif du client) ou "dette" (enregistre
     * explicitement ce paiement comme de la dette). Null tant que la
     * commande n'est pas payée.
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $modePaiement = null;

    /**
     * Utilisé uniquement pour une commande saisie manuellement en mode
     * simplifié (juste un nombre d'articles, sans détailler chaque
     * produit). Reste null pour une commande en ligne classique, où le
     * détail vient des lignes de commande.
     */
    #[ORM\Column(nullable: true)]
    private ?int $nombreArticlesManuel = null;

    /**
     * Null pour une commande saisie manuellement (elle n'est pas liée à un
     * jour de retrait précis) — toujours renseigné pour une commande en ligne.
     */
    #[ORM\ManyToOne(targetEntity: JourRetrait::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?JourRetrait $jourRetrait = null;

    /**
     * Date calendaire précise du retrait (ex: 2026-08-27), calculée au moment
     * de la commande à partir du jour de la semaine choisi. Contrairement à
     * JourRetrait (qui ne porte que le nom du jour, ex: "jeudi", partagé par
     * toutes les semaines), ce champ permet de distinguer une commande pour
     * "jeudi prochain" d'une commande pour "jeudi de la semaine dernière".
     */
    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $dateRetrait = null;

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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getMontantPaye(): ?string
    {
        return $this->montantPaye;
    }

    /**
     * Enregistre le montant payé et fixe automatiquement la date de
     * paiement à maintenant.
     */
    public function setMontantPaye(string $montantPaye): static
    {
        $this->montantPaye = $montantPaye;
        $this->datePaiement = new \DateTimeImmutable();

        return $this;
    }

    public function getDatePaiement(): ?\DateTimeImmutable
    {
        return $this->datePaiement;
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?string $modePaiement): static
    {
        $this->modePaiement = $modePaiement;

        return $this;
    }

    /**
     * Libellé lisible du mode de paiement, pour l'affichage dans l'historique.
     */
    public function getLibelleModePaiement(): string
    {
        return match ($this->modePaiement) {
            'solde' => 'Solde client',
            'dette' => 'Dette',
            'especes' => 'Espèces / Carte',
            default => '',
        };
    }

    public function estPayee(): bool
    {
        return null !== $this->montantPaye;
    }

    public function getNombreArticlesManuel(): ?int
    {
        return $this->nombreArticlesManuel;
    }

    public function setNombreArticlesManuel(?int $nombreArticlesManuel): static
    {
        $this->nombreArticlesManuel = $nombreArticlesManuel;

        return $this;
    }

    /**
     * Vrai si cette commande n'a pas de lignes détaillées (saisie manuelle
     * simplifiée) — dans ce cas, seul getNombreArticlesManuel() renseigne
     * la quantité, il n'y a pas de détail produit par produit.
     */
    public function estSaisieSimplifiee(): bool
    {
        return $this->lignes->isEmpty() && null !== $this->nombreArticlesManuel;
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

    public function getDateRetrait(): ?\DateTimeImmutable
    {
        return $this->dateRetrait;
    }

    public function setDateRetrait(\DateTimeImmutable $dateRetrait): static
    {
        $this->dateRetrait = $dateRetrait;

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
}
