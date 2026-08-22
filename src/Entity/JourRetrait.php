<?php

namespace App\Entity;

use App\Repository\JourRetraitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Représente un jour de la semaine où le retrait des commandes est possible
 * (ex : jeudi, vendredi, samedi...). La commande, elle, peut être passée
 * n'importe quel jour — c'est uniquement le RETRAIT qui est restreint.
 */
#[ORM\Entity(repositoryClass: JourRetraitRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_jour', columns: ['jour'])]
class JourRetrait
{
    public const JOURS_VALIDES = [
        'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Nom du jour en minuscules, ex: "jeudi". Doit être une valeur de JOURS_VALIDES.
     */
    #[ORM\Column(length: 20)]
    private ?string $jour = null;

    #[ORM\Column]
    private bool $actif = true;

    /**
     * @var Collection<int, StockProduit>
     */
    #[ORM\OneToMany(mappedBy: 'jourRetrait', targetEntity: StockProduit::class, orphanRemoval: true)]
    private Collection $stocks;

    public function __construct()
    {
        $this->stocks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJour(): ?string
    {
        return $this->jour;
    }

    public function setJour(string $jour): static
    {
        $this->jour = strtolower($jour);

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

    /**
     * @return Collection<int, StockProduit>
     */
    public function getStocks(): Collection
    {
        return $this->stocks;
    }
}
