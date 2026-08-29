<?php

namespace App\Entity;

use App\Repository\LigneCommandeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LigneCommandeRepository::class)]
class LigneCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commande $commande = null;

    #[ORM\ManyToOne(targetEntity: Produit::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Produit $produit = null;

    /**
     * Renseigné uniquement pour un produit vendu au kilo : la variante
     * précise choisie (ex: "500g"). Null pour un produit vendu à la pièce.
     */
    #[ORM\ManyToOne(targetEntity: VarianteProduit::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?VarianteProduit $varianteProduit = null;

    #[ORM\Column]
    private int $quantite = 1;

    /**
     * Prix affiché pour cette ligne au moment de la commande (celui du
     * produit s'il est vendu à la pièce, celui de la variante choisie
     * s'il est vendu au kilo). Purement informatif : on ne le multiplie
     * jamais par la quantité, on l'affiche tel quel.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $prixUnitaire = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;

        return $this;
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

    public function getVarianteProduit(): ?VarianteProduit
    {
        return $this->varianteProduit;
    }

    public function setVarianteProduit(?VarianteProduit $varianteProduit): static
    {
        $this->varianteProduit = $varianteProduit;

        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getPrixUnitaire(): ?string
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(string $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;

        return $this;
    }

    /**
     * Libellé complet de ce qui a été commandé, ex: "Pain de campagne (500g)"
     * ou juste "Baguette" pour un produit à la pièce.
     */
    public function getLibelleComplet(): string
    {
        if (null !== $this->varianteProduit) {
            return sprintf('%s (%s)', $this->produit?->getNom(), $this->varianteProduit->getLibelle());
        }

        return (string) $this->produit?->getNom();
    }
}
