<?php

namespace App\Entity;

use App\Repository\ProduitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProduitRepository::class)]
class Produit
{
    public const UNITE_PIECE = 'piece';
    public const UNITE_KILO = 'kilo';
    public const UNITES_VALIDES = [self::UNITE_PIECE, self::UNITE_KILO];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Vendu à la pièce (ex: une baguette) ou au kilo (ex: un pain vendu par
     * portions de 250g/500g/1kg, chaque portion ayant son propre prix fixé
     * par l'admin — jamais calculé automatiquement à partir d'un prix/kg).
     */
    #[ORM\Column(length: 10)]
    private string $unite = self::UNITE_PIECE;

    /**
     * Prix fixé par l'admin, affiché tel quel au client sans aucun calcul :
     * prix à la pièce pour un produit vendu à l'unité, prix au kg pour un
     * produit vendu au kilo (les variantes ne servent qu'à distinguer le
     * stock par format, elles n'ont pas de prix propre).
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prix = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\ManyToOne(targetEntity: Categorie::class, inversedBy: 'produits')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Categorie $categorie = null;

    /**
     * Nom du fichier image stocké dans public/uploads/produits/ (ex:
     * "6710a1b2c3.jpg"). Null si aucune image n'a été ajoutée.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /**
     * @var Collection<int, StockProduit>
     */
    #[ORM\OneToMany(mappedBy: 'produit', targetEntity: StockProduit::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $stocks;

    /**
     * @var Collection<int, VarianteProduit>
     */
    #[ORM\OneToMany(mappedBy: 'produit', targetEntity: VarianteProduit::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $variantes;

    public function __construct()
    {
        $this->stocks = new ArrayCollection();
        $this->variantes = new ArrayCollection();
    }

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getUnite(): string
    {
        return $this->unite;
    }

    public function setUnite(string $unite): static
    {
        if (!\in_array($unite, self::UNITES_VALIDES, true)) {
            throw new \InvalidArgumentException(sprintf('Unité invalide : "%s".', $unite));
        }

        $this->unite = $unite;

        return $this;
    }

    public function estVenduAuKilo(): bool
    {
        return self::UNITE_KILO === $this->unite;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(?string $prix): static
    {
        $this->prix = $prix;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getCategorie(): ?Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(?Categorie $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @return Collection<int, StockProduit>
     */
    public function getStocks(): Collection
    {
        return $this->stocks;
    }

    /**
     * Récupère le stock de ce produit (vendu à la pièce) pour un jour de
     * retrait donné, s'il existe.
     */
    public function getStockPourJour(JourRetrait $jourRetrait): ?StockProduit
    {
        foreach ($this->stocks as $stock) {
            if ($stock->getJourRetrait() === $jourRetrait) {
                return $stock;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, VarianteProduit>
     */
    public function getVariantes(): Collection
    {
        return $this->variantes;
    }
}
