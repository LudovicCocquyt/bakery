<?php

namespace App\Entity;

use App\Repository\VarianteProduitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une variante de vente au kilo pour un produit (ex: "250g", "500g", "1kg").
 * Une variante n'a pas de prix propre : c'est le prix au kg du produit
 * (Produit::prix) qui est affiché tel quel au client, sans aucun calcul.
 * La variante ne sert qu'à distinguer le stock par format.
 */
#[ORM\Entity(repositoryClass: VarianteProduitRepository::class)]
class VarianteProduit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Produit::class, inversedBy: 'variantes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Produit $produit = null;

    /**
     * Libellé affiché au client, ex: "250g", "500g", "1kg".
     */
    #[ORM\Column(length: 50)]
    private ?string $libelle = null;

    /**
     * @var Collection<int, StockVariante>
     */
    #[ORM\OneToMany(mappedBy: 'varianteProduit', targetEntity: StockVariante::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $stocks;

    public function __construct()
    {
        $this->stocks = new ArrayCollection();
    }

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

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    /**
     * @return Collection<int, StockVariante>
     */
    public function getStocks(): Collection
    {
        return $this->stocks;
    }

    public function getStockPourJour(JourRetrait $jourRetrait): ?StockVariante
    {
        foreach ($this->stocks as $stock) {
            if ($stock->getJourRetrait() === $jourRetrait) {
                return $stock;
            }
        }

        return null;
    }
}
