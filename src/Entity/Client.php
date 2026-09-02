<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fiche client pour le programme de fidélisation : historique des
 * commandes et des remises accordées. Chaque commande en ligne est
 * automatiquement rattachée à une fiche existante (retrouvée par email ou
 * téléphone) ou à une fiche nouvellement créée.
 */
#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_client_nom_prenom', columns: ['nom_prenom'])]
#[ORM\UniqueConstraint(name: 'uniq_client_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_client_telephone', columns: ['telephone'])]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nomPrenom = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    /**
     * Solde client, signé : positif = réserve d'argent disponible,
     * négatif = dette, zéro = neutre. Ne se modifie jamais directement :
     * uniquement via un dépôt (ajouterSolde) ou un paiement de commande
     * réglé "par solde" — chaque mouvement est tracé dans l'historique.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $solde = '0.00';

    /**
     * @var Collection<int, Commande>
     */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Commande::class)]
    private Collection $commandes;

    /**
     * @var Collection<int, Remise>
     */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Remise::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $remises;

    /**
     * @var Collection<int, MouvementSolde>
     */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: MouvementSolde::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $mouvementsSolde;

    public function __construct()
    {
        $this->commandes = new ArrayCollection();
        $this->remises = new ArrayCollection();
        $this->mouvementsSolde = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomPrenom(): ?string
    {
        return $this->nomPrenom;
    }

    public function setNomPrenom(string $nomPrenom): static
    {
        $this->nomPrenom = $nomPrenom;

        return $this;
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

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getSolde(): string
    {
        return $this->solde;
    }

    public function setSolde(string $solde): static
    {
        $this->solde = $solde;

        return $this;
    }

    /**
     * @return Collection<int, Commande>
     */
    public function getCommandes(): Collection
    {
        return $this->commandes;
    }

    /**
     * @return Collection<int, Remise>
     */
    public function getRemises(): Collection
    {
        return $this->remises;
    }

    /**
     * @return Collection<int, MouvementSolde>
     */
    public function getMouvementsSolde(): Collection
    {
        return $this->mouvementsSolde;
    }

    /**
     * Historique fusionné commandes + remises + mouvements de solde, trié
     * de la plus récente à la plus ancienne. Chaque entrée est un tableau
     * ['type' => 'commande'|'remise'|'mouvement', 'date' => ..., 'objet' => ...].
     *
     * @return array<int, array{type: string, date: \DateTimeImmutable, objet: Commande|Remise|MouvementSolde}>
     */
    public function getHistorique(): array
    {
        $historique = [];

        foreach ($this->commandes as $commande) {
            $historique[] = ['type' => 'commande', 'date' => $commande->getDateCommande(), 'objet' => $commande];
        }

        foreach ($this->remises as $remise) {
            $historique[] = ['type' => 'remise', 'date' => $remise->getDate(), 'objet' => $remise];
        }

        foreach ($this->mouvementsSolde as $mouvement) {
            $historique[] = ['type' => 'mouvement', 'date' => $mouvement->getDate(), 'objet' => $mouvement];
        }

        // Tri décroissant : l'entrée la plus récente en premier.
        usort($historique, fn ($a, $b) => $b['date'] <=> $a['date']);

        return $historique;
    }
}
