<?php

namespace App\Repository;

use App\Entity\JourRetrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JourRetrait>
 */
class JourRetraitRepository extends ServiceEntityRepository
{
    /**
     * Ordre naturel de la semaine (lundi en premier), utilisé pour trier
     * les jours à l'affichage — l'ordre d'insertion en base (id) ne
     * correspond pas forcément à l'ordre chronologique de la semaine.
     */
    private const ORDRE_SEMAINE = [
        'lundi' => 1,
        'mardi' => 2,
        'mercredi' => 3,
        'jeudi' => 4,
        'vendredi' => 5,
        'samedi' => 6,
        'dimanche' => 7,
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JourRetrait::class);
    }

    public function trouverParNomJour(string $jour): ?JourRetrait
    {
        return $this->findOneBy(['jour' => strtolower($jour)]);
    }

    /**
     * Tous les jours de retrait, triés dans l'ordre naturel de la semaine
     * (lundi, mardi, ..., dimanche) plutôt que par ordre d'insertion.
     *
     * @return JourRetrait[]
     */
    public function trouverTriesParJourSemaine(): array
    {
        $jours = $this->findAll();
        usort($jours, fn (JourRetrait $a, JourRetrait $b) =>
            (self::ORDRE_SEMAINE[$a->getJour()] ?? 99) <=> (self::ORDRE_SEMAINE[$b->getJour()] ?? 99)
        );

        return $jours;
    }

    /**
     * Comme trouverTriesParJourSemaine(), mais uniquement les jours actifs.
     *
     * @return JourRetrait[]
     */
    public function trouverActifsTriesParJourSemaine(): array
    {
        return array_values(array_filter(
            $this->trouverTriesParJourSemaine(),
            fn (JourRetrait $j) => $j->isActif()
        ));
    }

    /**
     * @return JourRetrait[]
     */
    public function trouverActifs(): array
    {
        return $this->findBy(['actif' => true]);
    }
}
