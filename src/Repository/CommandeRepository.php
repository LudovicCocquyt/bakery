<?php

namespace App\Repository;

use App\Entity\Commande;
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
     * Toutes les commandes pour une date de retrait précise (ex: le jeudi
     * 27 août, pas "tous les jeudis confondus"), lignes et produits déjà
     * chargés pour éviter le N+1 sur la page admin.
     *
     * @return Commande[]
     */
    public function trouverParDateRetrait(\DateTimeImmutable $dateRetrait): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('l', 'p')
            ->leftJoin('c.lignes', 'l')
            ->leftJoin('l.produit', 'p')
            ->andWhere('c.dateRetrait = :date')
            ->andWhere('c.statut != :annulee')
            ->setParameter('date', $dateRetrait)
            ->setParameter('annulee', Commande::STATUT_ANNULEE)
            ->orderBy('c.dateCommande', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Les dates de retrait distinctes ayant au moins une commande, triées de
     * la plus récente à la plus ancienne. Sert à construire la navigation
     * "date précédente / date suivante" côté admin sans avoir à deviner
     * quelles dates existent réellement.
     *
     * @return \DateTimeImmutable[]
     */
    public function trouverDatesRetraitAvecCommandes(): array
    {
        $resultats = $this->createQueryBuilder('c')
            ->select('DISTINCT c.dateRetrait AS dateRetrait')
            ->andWhere('c.statut != :annulee')
            ->setParameter('annulee', Commande::STATUT_ANNULEE)
            ->orderBy('c.dateRetrait', 'DESC')
            ->getQuery()
            ->getScalarResult();

        return array_map(
            fn (array $ligne) => new \DateTimeImmutable($ligne['dateRetrait']),
            $resultats
        );
    }
}
