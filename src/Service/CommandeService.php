<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\JourRetrait;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Repository\StockProduitRepository;
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
    ) {
        parent::__construct(sprintf(
            'Stock insuffisant pour "%s" : %d demandé(s), %d disponible(s).',
            $produit->getNom(),
            $demande,
            $disponible
        ));
    }
}

class JourRetraitInactifException extends \RuntimeException
{
}

/**
 * Item d'entrée pour passerCommande() : un produit + une quantité demandée.
 */
final class LigneDemandee
{
    public function __construct(
        public readonly Produit $produit,
        public readonly int $quantite,
    ) {
    }
}

class CommandeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockProduitRepository $stockProduitRepository,
    ) {
    }

    /**
     * Passe une commande : vérifie que le jour est actif, que le stock est
     * suffisant pour chaque ligne, décrémente le stock, puis enregistre la
     * commande. Tout se fait dans une seule transaction avec verrous sur les
     * lignes de stock concernées, pour être sûr qu'on ne survend jamais un
     * produit même en cas de commandes simultanées.
     *
     * @param LigneDemandee[] $lignesDemandees
     *
     * @throws JourRetraitInactifException si le jour de retrait n'est pas actif
     * @throws StockInsuffisantException   si un produit n'a plus assez de stock
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

            foreach ($lignesDemandees as $demande) {
                if ($demande->quantite <= 0) {
                    throw new \InvalidArgumentException('La quantité doit être positive.');
                }

                // Verrou pessimiste : bloque la ligne de stock jusqu'à la fin
                // de la transaction pour empêcher une lecture concurrente du
                // même stock pendant qu'on le décrémente.
                $stock = $this->stockProduitRepository->trouverAvecVerrou($demande->produit, $jourRetrait);

                if (null === $stock) {
                    // Le produit n'est simplement pas proposé ce jour-là.
                    throw new StockInsuffisantException($demande->produit, $demande->quantite, 0);
                }

                if ($stock->getStockRestant() < $demande->quantite) {
                    throw new StockInsuffisantException(
                        $demande->produit,
                        $demande->quantite,
                        $stock->getStockRestant()
                    );
                }

                $stock->decrementer($demande->quantite);

                $ligne = new LigneCommande();
                $ligne->setProduit($demande->produit);
                $ligne->setQuantite($demande->quantite);
                $ligne->setPrixUnitaire($demande->produit->getPrix());
                $commande->ajouterLigne($ligne);
            }

            $commande->setStatut(Commande::STATUT_CONFIRMEE);

            $this->entityManager->persist($commande);
            // Le flush se fait à l'intérieur de wrapInTransaction : si une
            // exception est levée avant, rien n'est écrit (rollback complet,
            // stock inclus).

            return $commande;
        });
    }
}
