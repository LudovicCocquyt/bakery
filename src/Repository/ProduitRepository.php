<?php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /**
     * @return Produit[]
     */
    public function trouverActifs(): array
    {
        return $this->findBy(['actif' => true]);
    }

    /**
     * Tous les produits, triés par catégorie (ordre d'affichage puis nom),
     * les produits sans catégorie regroupés à la fin, puis par nom de produit.
     * Sert à l'affichage de la liste admin.
     *
     * @return Produit[]
     */
    public function trouverTousTriesParCategorie(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.categorie', 'c')
            ->addSelect('c')
            ->orderBy('CASE WHEN p.categorie IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('c.ordre', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->addOrderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
