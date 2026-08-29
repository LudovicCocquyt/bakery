<?php

namespace App\Command;

use App\Repository\JourRetraitRepository;
use App\Repository\StockProduitRepository;
use App\Repository\StockVarianteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Réinitialise le stock_restant = stock_initial pour un jour de retrait.
 *
 * Sans argument : réinitialise le jour correspondant à AUJOURD'HUI (utile
 * pour le cron o2switch qui tourne tous les jours à 00:01 — chaque jour
 * ne fait quelque chose que si un JourRetrait actif porte son nom).
 *
 * Avec argument : `php bin/console app:reset-stock samedi` force le reset
 * d'un jour précis, pratique pour les tests ou un rattrapage manuel.
 */
#[AsCommand(
    name: 'app:reset-stock',
    description: 'Réinitialise le stock restant au stock initial pour un jour de retrait donné (ou aujourd\'hui par défaut).',
)]
class ResetStockCommand extends Command
{
    private const JOURS_PHP = [
        'Monday' => 'lundi',
        'Tuesday' => 'mardi',
        'Wednesday' => 'mercredi',
        'Thursday' => 'jeudi',
        'Friday' => 'vendredi',
        'Saturday' => 'samedi',
        'Sunday' => 'dimanche',
    ];

    public function __construct(
        private readonly JourRetraitRepository $jourRetraitRepository,
        private readonly StockProduitRepository $stockProduitRepository,
        private readonly StockVarianteRepository $stockVarianteRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'jour',
                InputArgument::OPTIONAL,
                'Jour à réinitialiser (ex: samedi). Par défaut : jour actuel.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $jourDemande = $input->getArgument('jour');
        $nomJour = $jourDemande ? strtolower($jourDemande) : self::JOURS_PHP[date('l')];

        $jourRetrait = $this->jourRetraitRepository->trouverParNomJour($nomJour);

        if (null === $jourRetrait) {
            // Pas une erreur : la plupart des jours de la semaine n'ont pas
            // de JourRetrait associé (ex: lundi si seuls jeudi/vendredi/samedi
            // sont configurés). Le cron peut tourner tous les jours sans souci.
            $io->comment(sprintf('Aucun jour de retrait "%s" configuré, rien à faire.', $nomJour));

            return Command::SUCCESS;
        }

        if (!$jourRetrait->isActif()) {
            $io->comment(sprintf('Le jour de retrait "%s" est désactivé, reset ignoré.', $nomJour));

            return Command::SUCCESS;
        }

        $stocks = $this->stockProduitRepository->trouverParJour($jourRetrait);

        foreach ($stocks as $stock) {
            $stock->reinitialiser();
        }

        $stocksVariantes = $this->stockVarianteRepository->trouverParJour($jourRetrait);

        foreach ($stocksVariantes as $stock) {
            $stock->reinitialiser();
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d ligne(s) de stock produit + %d ligne(s) de stock variante réinitialisée(s) pour "%s".',
            count($stocks),
            count($stocksVariantes),
            $nomJour
        ));

        return Command::SUCCESS;
    }
}
