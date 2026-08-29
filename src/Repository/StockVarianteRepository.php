<?php

namespace App\Repository;

use App\Entity\JourRetrait;
use App\Entity\StockVariante;
use App\Entity\VarianteProduit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockVariante>
 */
class StockVarianteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockVariante::class);
    }

    /**
     * Même principe que StockProduitRepository::trouverAvecVerrou : verrouille
     * la ligne de stock (SELECT ... FOR UPDATE) pour éviter la survente en cas
     * de commandes simultanées sur la même variante. À utiliser dans une transaction.
     */
    public function trouverAvecVerrou(VarianteProduit $varianteProduit, JourRetrait $jourRetrait): ?StockVariante
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.varianteProduit = :variante')
            ->andWhere('s.jourRetrait = :jour')
            ->setParameter('variante', $varianteProduit)
            ->setParameter('jour', $jourRetrait)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    /**
     * @return StockVariante[]
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
