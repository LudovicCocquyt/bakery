<?php

namespace App\Entity;

use App\Repository\StockVarianteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Stock d'une variante de produit (ex: les "250g" du pain de campagne) pour
 * un jour de retrait donné. Fonctionne exactement comme StockProduit, mais
 * pour les variantes des produits vendus au kilo — chaque variante a son
 * propre stock, indépendant des autres variantes du même produit.
 */
#[ORM\Entity(repositoryClass: StockVarianteRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_variante_jour', columns: ['variante_produit_id', 'jour_retrait_id'])]
class StockVariante
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VarianteProduit::class, inversedBy: 'stocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?VarianteProduit $varianteProduit = null;

    #[ORM\ManyToOne(targetEntity: JourRetrait::class)]
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

    public function getVarianteProduit(): ?VarianteProduit
    {
        return $this->varianteProduit;
    }

    public function setVarianteProduit(?VarianteProduit $varianteProduit): static
    {
        $this->varianteProduit = $varianteProduit;

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

    public function reinitialiser(): static
    {
        $this->stockRestant = $this->stockInitial;

        return $this;
    }

    public function decrementer(int $quantite): static
    {
        if ($quantite > $this->stockRestant) {
            throw new \RuntimeException(sprintf(
                'Stock insuffisant pour la variante #%d le %s : demandé %d, restant %d.',
                $this->varianteProduit?->getId(),
                $this->jourRetrait?->getJour(),
                $quantite,
                $this->stockRestant
            ));
        }

        $this->stockRestant -= $quantite;

        return $this;
    }
}
