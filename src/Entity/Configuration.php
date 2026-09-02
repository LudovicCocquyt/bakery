<?php

namespace App\Entity;

use App\Repository\ConfigurationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Réglages globaux du site, sous forme d'une seule ligne en base (singleton).
 * Couvre l'activation des fonctionnalités (fiche client, réserve d'argent),
 * le mode de calcul de la fidélité, et le thème visuel (police, tailles,
 * couleurs) — modifiable depuis /admin/configuration.
 */
#[ORM\Entity(repositoryClass: ConfigurationRepository::class)]
class Configuration
{
    public const FIDELITE_AUCUN = 'aucun';
    public const FIDELITE_PASSAGES = 'passages';
    public const FIDELITE_ARTICLES = 'articles';
    public const FIDELITE_EUROS = 'euros';
    public const FIDELITE_MODES_VALIDES = [
        self::FIDELITE_AUCUN,
        self::FIDELITE_PASSAGES,
        self::FIDELITE_ARTICLES,
        self::FIDELITE_EUROS,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private bool $ficheClientActivee = true;

    /**
     * Nom du site affiché dans l'en-tête (ex: "🥐 La Boulangerie").
     */
    #[ORM\Column(length: 255)]
    private string $nomSite = '🥐 La Boulangerie';

    /**
     * URL externe vers le site web principal de la boulangerie. Si vide,
     * aucun bouton de retour n'est affiché dans l'en-tête pour les
     * visiteurs non connectés (il n'y a jamais de lien vers l'admin
     * affiché publiquement).
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $urlRetourSite = null;

    #[ORM\Column(length: 100)]
    private string $nomBoutonRetour = 'Retour au site';

    /**
     * Active le solde client (réserve d'argent / dette fusionnés en un
     * unique solde signé sur la fiche client).
     */
    #[ORM\Column]
    private bool $soldeClientActivee = false;

    /**
     * Mode de déclenchement de la remise fidélité : nombre de passages
     * (commandes), nombre d'articles achetés, ou montant total dépensé.
     * "aucun" désactive toute détection automatique — les remises restent
     * alors toujours possibles manuellement depuis la fiche client.
     */
    #[ORM\Column(length: 20)]
    private string $fideliteMode = self::FIDELITE_AUCUN;

    /**
     * Seuil à atteindre pour déclencher la détection (ex: tous les 10
     * passages, tous les 20 articles, tous les 100 € dépensés).
     */
    #[ORM\Column(nullable: true)]
    private ?int $fideliteSeuil = null;

    #[ORM\Column(length: 255)]
    private string $policeTexte = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";

    #[ORM\Column]
    private int $tailleTexteBase = 16;

    #[ORM\Column]
    private int $tailleTitre1 = 28;

    #[ORM\Column]
    private int $tailleTitre2 = 20;

    #[ORM\Column(length: 20)]
    private string $couleurPrincipale = '#b5651d';

    #[ORM\Column(length: 20)]
    private string $couleurTexte = '#2b2118';

    #[ORM\Column(length: 20)]
    private string $couleurFond = '#fdf8f2';

    #[ORM\Column(length: 20)]
    private string $couleurBordure = '#e6dcd0';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isFicheClientActivee(): bool
    {
        return $this->ficheClientActivee;
    }

    public function setFicheClientActivee(bool $ficheClientActivee): static
    {
        $this->ficheClientActivee = $ficheClientActivee;

        return $this;
    }

    public function getNomSite(): string
    {
        return $this->nomSite;
    }

    public function setNomSite(string $nomSite): static
    {
        $this->nomSite = $nomSite;

        return $this;
    }

    public function getUrlRetourSite(): ?string
    {
        return $this->urlRetourSite;
    }

    public function setUrlRetourSite(?string $urlRetourSite): static
    {
        $this->urlRetourSite = $urlRetourSite;

        return $this;
    }

    public function getNomBoutonRetour(): string
    {
        return $this->nomBoutonRetour;
    }

    public function setNomBoutonRetour(string $nomBoutonRetour): static
    {
        $this->nomBoutonRetour = $nomBoutonRetour;

        return $this;
    }

    public function isSoldeClientActivee(): bool
    {
        return $this->soldeClientActivee;
    }

    public function setSoldeClientActivee(bool $soldeClientActivee): static
    {
        $this->soldeClientActivee = $soldeClientActivee;

        return $this;
    }

    public function getFideliteMode(): string
    {
        return $this->fideliteMode;
    }

    public function setFideliteMode(string $fideliteMode): static
    {
        if (!\in_array($fideliteMode, self::FIDELITE_MODES_VALIDES, true)) {
            throw new \InvalidArgumentException(sprintf('Mode de fidélité invalide : "%s".', $fideliteMode));
        }

        $this->fideliteMode = $fideliteMode;

        return $this;
    }

    public function getFideliteSeuil(): ?int
    {
        return $this->fideliteSeuil;
    }

    public function setFideliteSeuil(?int $fideliteSeuil): static
    {
        $this->fideliteSeuil = $fideliteSeuil;

        return $this;
    }

    public function getPoliceTexte(): string
    {
        return $this->policeTexte;
    }

    public function setPoliceTexte(string $policeTexte): static
    {
        $this->policeTexte = $policeTexte;

        return $this;
    }

    public function getTailleTexteBase(): int
    {
        return $this->tailleTexteBase;
    }

    public function setTailleTexteBase(int $tailleTexteBase): static
    {
        $this->tailleTexteBase = $tailleTexteBase;

        return $this;
    }

    public function getTailleTitre1(): int
    {
        return $this->tailleTitre1;
    }

    public function setTailleTitre1(int $tailleTitre1): static
    {
        $this->tailleTitre1 = $tailleTitre1;

        return $this;
    }

    public function getTailleTitre2(): int
    {
        return $this->tailleTitre2;
    }

    public function setTailleTitre2(int $tailleTitre2): static
    {
        $this->tailleTitre2 = $tailleTitre2;

        return $this;
    }

    public function getCouleurPrincipale(): string
    {
        return $this->couleurPrincipale;
    }

    public function setCouleurPrincipale(string $couleurPrincipale): static
    {
        $this->couleurPrincipale = $couleurPrincipale;

        return $this;
    }

    public function getCouleurTexte(): string
    {
        return $this->couleurTexte;
    }

    public function setCouleurTexte(string $couleurTexte): static
    {
        $this->couleurTexte = $couleurTexte;

        return $this;
    }

    public function getCouleurFond(): string
    {
        return $this->couleurFond;
    }

    public function setCouleurFond(string $couleurFond): static
    {
        $this->couleurFond = $couleurFond;

        return $this;
    }

    public function getCouleurBordure(): string
    {
        return $this->couleurBordure;
    }

    public function setCouleurBordure(string $couleurBordure): static
    {
        $this->couleurBordure = $couleurBordure;

        return $this;
    }
}
