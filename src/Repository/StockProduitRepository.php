<?php

namespace App\Repository;

use App\Entity\JourRetrait;
use App\Entity\Produit;
use App\Entity\StockProduit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockProduit>
 */
class StockProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockProduit::class);
    }

    /**
     * Récupère le stock d'un produit pour un jour donné en le verrouillant
     * (SELECT ... FOR UPDATE). Indispensable pour éviter que deux commandes
     * simultanées ne décrémentent le même stock en même temps et le fassent
     * passer en négatif (race condition classique sur un dernier article).
     *
     * À utiliser uniquement à l'intérieur d'une transaction Doctrine.
     */
    public function trouverAvecVerrou(Produit $produit, JourRetrait $jourRetrait): ?StockProduit
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.produit = :produit')
            ->andWhere('s.jourRetrait = :jour')
            ->setParameter('produit', $produit)
            ->setParameter('jour', $jourRetrait)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    /**
     * Tous les stocks liés à un jour de retrait (utilisé par le reset hebdo).
     *
     * @return StockProduit[]
     */
    public function trouverParJour(JourRetrait $jourRetrait): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.jourRetrait = :jour')
            ->setParameter('jour', $jourRetrait)
            ->getQuery()
            ->getResult();
    }
}
