<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\JourRetrait;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Entity\VarianteProduit;
use App\Repository\StockProduitRepository;
use App\Repository\StockVarianteRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Exception dédiée pour distinguer "stock insuffisant" d'une erreur technique
 * quelconque, afin que le contrôleur puisse afficher un message clair au client.
 */
class StockInsuffisantException extends \RuntimeException
{
    public function __construct(
        public readonly Produit $produit,
        public readonly int $demande,
        public readonly int $disponible,
        public readonly ?VarianteProduit $varianteProduit = null,
    ) {
        $libelle = null !== $varianteProduit
            ? sprintf('%s (%s)', $produit->getNom(), $varianteProduit->getLibelle())
            : $produit->getNom();

        parent::__construct(sprintf(
            'Stock insuffisant pour "%s" : %d demandé(s), %d disponible(s).',
            $libelle,
            $demande,
            $disponible
        ));
    }
}

class JourRetraitInactifException extends \RuntimeException
{
}

class VarianteInvalideException extends \RuntimeException
{
}

/**
 * Item d'entrée pour passerCommande() : un produit + une quantité demandée,
 * et pour un produit vendu au kilo, la variante précise choisie (ex: "500g").
 */
final class LigneDemandee
{
    public function __construct(
        public readonly Produit $produit,
        public readonly int $quantite,
        public readonly ?VarianteProduit $varianteProduit = null,
    ) {
    }
}

class CommandeService
{
    private const JOURS_PHP = [
        'lundi' => 'Monday',
        'mardi' => 'Tuesday',
        'mercredi' => 'Wednesday',
        'jeudi' => 'Thursday',
        'vendredi' => 'Friday',
        'samedi' => 'Saturday',
        'dimanche' => 'Sunday',
    ];

    /**
     * Heure de la veille à partir de laquelle la réservation d'un jour de
     * retrait est bloquée (ex: 22 => à partir de 22h la veille, on ne peut
     * plus réserver pour le lendemain — s'applique à chaque jour de retrait
     * de la même façon).
     */
    private const HEURE_LIMITE_RESERVATION = 22;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockProduitRepository $stockProduitRepository,
        private readonly StockVarianteRepository $stockVarianteRepository,
    ) {
    }

    /**
     * Calcule la prochaine date de retrait réellement disponible pour un
     * jour de la semaine donné (ex: "jeudi"), en tenant compte de la coupure
     * de réservation : si on est déjà passé l'heure limite la veille de la
     * prochaine occurrence, on passe automatiquement à la semaine suivante.
     *
     * Exemple : jeudi 18h → "jeudi" retourne demain. Mercredi 23h (donc après
     * 22h) → "jeudi" retourne le jeudi de la semaine suivante, plus celui du
     * lendemain, car la réservation pour demain est déjà fermée.
     */
    public function prochaineDateDisponible(string $nomJour): \DateTimeImmutable
    {
        $date = $this->prochaineOccurrence($nomJour);

        while (!$this->estEncoreReservable($date)) {
            $date = $date->modify('+7 days');
        }

        return $date;
    }

    /**
     * Vraie si la date de retrait donnée est encore réservable maintenant,
     * c'est-à-dire qu'on n'a pas dépassé l'heure limite de la veille.
     */
    private function estEncoreReservable(\DateTimeImmutable $dateRetrait): bool
    {
        $limite = $dateRetrait
            ->modify('-1 day')
            ->setTime(self::HEURE_LIMITE_RESERVATION, 0);

        return new \DateTimeImmutable('now') < $limite;
    }

    /**
     * Prochaine occurrence calendaire d'un jour de la semaine donné (ex:
     * "jeudi"), en partant d'aujourd'hui inclus — sans tenir compte de la
     * coupure de réservation (voir prochaineDateDisponible pour ça).
     */
    private function prochaineOccurrence(string $nomJour): \DateTimeImmutable
    {
        $aujourdhui = new \DateTimeImmutable('today');
        $jourPhp = self::JOURS_PHP[$nomJour] ?? null;

        if (null === $jourPhp) {
            throw new \InvalidArgumentException(sprintf('Jour inconnu : "%s".', $nomJour));
        }

        if ($aujourdhui->format('l') === $jourPhp) {
            return $aujourdhui;
        }

        return $aujourdhui->modify('next '.$jourPhp);
    }

    /**
     * Passe une commande : vérifie que le jour est actif, que le stock est
     * suffisant pour chaque ligne (produit à la pièce OU variante au kilo),
     * décrémente le stock, puis enregistre la commande. Tout se fait dans une
     * seule transaction avec verrous sur les lignes de stock concernées, pour
     * ne jamais survendre même en cas de commandes simultanées.
     *
     * @param LigneDemandee[] $lignesDemandees
     *
     * @throws JourRetraitInactifException si le jour de retrait n'est pas actif
     * @throws StockInsuffisantException   si un produit/variante n'a plus assez de stock
     * @throws VarianteInvalideException   si la variante ne correspond pas au produit, ou est manquante/inattendue
     */
    public function passerCommande(
        string $nomClient,
        string $emailClient,
        ?string $telephoneClient,
        JourRetrait $jourRetrait,
        array $lignesDemandees,
    ): Commande {
        if (!$jourRetrait->isActif()) {
            throw new JourRetraitInactifException(sprintf(
                'Le jour de retrait "%s" n\'est pas actif.',
                $jourRetrait->getJour()
            ));
        }

        if ([] === $lignesDemandees) {
            throw new \InvalidArgumentException('Une commande doit contenir au moins une ligne.');
        }

        return $this->entityManager->wrapInTransaction(function () use (
            $nomClient,
            $emailClient,
            $telephoneClient,
            $jourRetrait,
            $lignesDemandees,
        ) {
            $commande = new Commande();
            $commande->setNomClient($nomClient);
            $commande->setEmailClient($emailClient);
            $commande->setTelephoneClient($telephoneClient);
            $commande->setJourRetrait($jourRetrait);
            $commande->setDateRetrait($this->prochaineDateDisponible($jourRetrait->getJour()));

            foreach ($lignesDemandees as $demande) {
                if ($demande->quantite <= 0) {
                    throw new \InvalidArgumentException('La quantité doit être positive.');
                }

                $produit = $demande->produit;
                $estAuKilo = $produit->estVenduAuKilo();

                if ($estAuKilo && null === $demande->varianteProduit) {
                    throw new VarianteInvalideException(sprintf(
                        'Une variante est requise pour le produit "%s" (vendu au kilo).',
                        $produit->getNom()
                    ));
                }

                if (!$estAuKilo && null !== $demande->varianteProduit) {
                    throw new VarianteInvalideException(sprintf(
                        'Le produit "%s" est vendu à la pièce, aucune variante n\'est attendue.',
                        $produit->getNom()
                    ));
                }

                if ($estAuKilo && $demande->varianteProduit->getProduit() !== $produit) {
                    throw new VarianteInvalideException('Cette variante ne correspond pas au produit demandé.');
                }

                $ligne = new LigneCommande();
                $ligne->setProduit($produit);
                $ligne->setQuantite($demande->quantite);

                if ($estAuKilo) {
                    $variante = $demande->varianteProduit;

                    // Verrou pessimiste : bloque la ligne de stock jusqu'à la
                    // fin de la transaction pour empêcher une lecture
                    // concurrente pendant qu'on la décrémente.
                    $stock = $this->stockVarianteRepository->trouverAvecVerrou($variante, $jourRetrait);

                    if (null === $stock) {
                        throw new StockInsuffisantException($produit, $demande->quantite, 0, $variante);
                    }

                    if ($stock->getStockRestant() < $demande->quantite) {
                        throw new StockInsuffisantException(
                            $produit,
                            $demande->quantite,
                            $stock->getStockRestant(),
                            $variante
                        );
                    }

                    $stock->decrementer($demande->quantite);

                    $ligne->setVarianteProduit($variante);
                    $ligne->setPrixUnitaire($produit->getPrix());
                } else {
                    $stock = $this->stockProduitRepository->trouverAvecVerrou($produit, $jourRetrait);

                    if (null === $stock) {
                        throw new StockInsuffisantException($produit, $demande->quantite, 0);
                    }

                    if ($stock->getStockRestant() < $demande->quantite) {
                        throw new StockInsuffisantException($produit, $demande->quantite, $stock->getStockRestant());
                    }

                    $stock->decrementer($demande->quantite);

                    $ligne->setPrixUnitaire($produit->getPrix());
                }

                $commande->ajouterLigne($ligne);
            }

            $commande->setStatut(Commande::STATUT_CONFIRMEE);

            $this->entityManager->persist($commande);

            return $commande;
        });
    }
}
