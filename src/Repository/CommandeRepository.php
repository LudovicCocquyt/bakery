<?php

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\JourRetrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * Toutes les commandes pour un jour de retrait donné, triées par date,
     * avec les lignes et produits déjà chargés (évite le N+1 sur la page admin).
     *
     * @return Commande[]
     */
    public function trouverParJour(JourRetrait $jourRetrait): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('l', 'p')
            ->leftJoin('c.lignes', 'l')
            ->leftJoin('l.produit', 'p')
            ->andWhere('c.jourRetrait = :jour')
            ->andWhere('c.statut != :annulee')
            ->setParameter('jour', $jourRetrait)
            ->setParameter('annulee', Commande::STATUT_ANNULEE)
            ->orderBy('c.dateCommande', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
