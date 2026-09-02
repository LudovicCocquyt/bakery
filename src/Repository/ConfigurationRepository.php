<?php

namespace App\Repository;

use App\Entity\Configuration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Configuration>
 */
class ConfigurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Configuration::class);
    }

    /**
     * Récupère l'unique ligne de configuration, ou la crée avec les valeurs
     * par défaut si elle n'existe pas encore (premier accès après migration).
     */
    public function getOuCreer(): Configuration
    {
        $configuration = $this->createQueryBuilder('c')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $configuration) {
            $configuration = new Configuration();
            $em = $this->getEntityManager();
            $em->persist($configuration);
            $em->flush();
        }

        return $configuration;
    }
}
