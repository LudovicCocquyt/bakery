<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function trouverParEmail(string $email): ?Client
    {
        return $this->createQueryBuilder('c')
            ->andWhere('LOWER(c.email) = LOWER(:email)')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function trouverParTelephone(string $telephone): ?Client
    {
        return $this->findOneBy(['telephone' => $telephone]);
    }

    /**
     * Recherche libre sur le nom, l'email ou le téléphone — permet de
     * retrouver facilement une fiche client avec un mail ou un numéro.
     *
     * @return Client[]
     */
    public function rechercher(string $terme): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.nomPrenom LIKE :terme OR c.email LIKE :terme OR c.telephone LIKE :terme')
            ->setParameter('terme', '%'.$terme.'%')
            ->orderBy('c.nomPrenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Client[]
     */
    public function trouverTousTries(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.nomPrenom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
