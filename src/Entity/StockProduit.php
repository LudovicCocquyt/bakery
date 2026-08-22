<?php

namespace App\Entity;

use App\Repository\StockProduitRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Stock d'un produit pour un jour de retrait donné.
 * C'est cette entité qui porte le stock_initial et le stock_restant,
 * et qui est remise à zéro chaque semaine par la commande de reset.
 */
#[ORM\Entity(repositoryClass: StockProduitRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_produit_jour', columns: ['produit_id', 'jour_retrait_id'])]
class StockProduit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Produit::class, inversedBy: 'stocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Produit $produit = null;

    #[ORM\ManyToOne(targetEntity: JourRetrait::class, inversedBy: 'stocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?JourRetrait $jourRetrait = null;

    #[ORM\Column]
    private int $stockInitial = 0;

    #[ORM\Column]
    private int $stockRestant = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(?Produit $produit): static
    {
        $this->produit = $produit;

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

    public function getStockInitial(): int
    {
        return $this->stockInitial;
    }

    /**
     * Modifie le stock initial. Ne touche PAS au stock restant :
     * c'est volontaire, pour ne pas fausser une semaine en cours.
     * Le nouveau stock initial ne s'appliquera qu'au prochain reset.
     */
    public function setStockInitial(int $stockInitial): static
    {
        $this->stockInitial = $stockInitial;

        return $this;
    }

    public function getStockRestant(): int
    {
        return $this->stockRestant;
    }

    public function setStockRestant(int $stockRestant): static
    {
        $this->stockRestant = $stockRestant;

        return $this;
    }

    public function estEpuise(): bool
    {
        return $this->stockRestant <= 0;
    }

    /**
     * Réinitialise le stock restant au niveau du stock initial.
     * Utilisé par la commande de reset hebdomadaire.
     */
    public function reinitialiser(): static
    {
        $this->stockRestant = $this->stockInitial;

        return $this;
    }

    /**
     * Décrémente le stock restant d'une quantité donnée.
     * Lève une exception si le stock est insuffisant, pour ne jamais
     * passer en négatif (protection en plus de la vérification en amont).
     */
    public function decrementer(int $quantite): static
    {
        if ($quantite > $this->stockRestant) {
            throw new \RuntimeException(sprintf(
                'Stock insuffisant pour le produit #%d le %s : demandé %d, restant %d.',
                $this->produit?->getId(),
                $this->jourRetrait?->getJour(),
                $quantite,
                $this->stockRestant
            ));
        }

        $this->stockRestant -= $quantite;

        return $this;
    }
}
