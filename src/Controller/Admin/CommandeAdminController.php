<?php

namespace App\Controller\Admin;

use App\Repository\CommandeRepository;
use App\Repository\JourRetraitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/commandes')]
#[IsGranted('ROLE_ADMIN')]
class CommandeAdminController extends AbstractController
{
    #[Route('', name: 'admin_commandes_index', methods: ['GET'])]
    public function index(
        Request $request,
        JourRetraitRepository $jourRetraitRepository,
        CommandeRepository $commandeRepository,
    ): Response {
        // Date choisie via ?date=2026-08-27, sinon aujourd'hui par défaut.
        $dateParametre = $request->query->get('date');
        try {
            $dateChoisie = $dateParametre
                ? new \DateTimeImmutable($dateParametre)
                : new \DateTimeImmutable('today');
        } catch (\Exception) {
            $dateChoisie = new \DateTimeImmutable('today');
        }
        // On ignore l'heure : on ne compare que la date calendaire.
        $dateChoisie = $dateChoisie->setTime(0, 0);

        $commandes = $commandeRepository->trouverParDateRetrait($dateChoisie);

        // Liste des dates ayant réellement des commandes, pour construire
        // une navigation "date précédente / date suivante" qui saute
        // directement d'une date connue à une autre (plutôt que de
        // proposer des jours vides un par un).
        $datesAvecCommandes = $commandeRepository->trouverDatesRetraitAvecCommandes();

        $dateSuivante = null;
        $datePrecedente = null;
        foreach ($datesAvecCommandes as $date) {
            if ($date > $dateChoisie && (null === $dateSuivante || $date < $dateSuivante)) {
                $dateSuivante = $date;
            }
            if ($date < $dateChoisie && (null === $datePrecedente || $date > $datePrecedente)) {
                $datePrecedente = $date;
            }
        }

        $nombreArticles = 0;
        /** @var array<string, array<string, int>> $totauxParCategorie */
        $totauxParCategorie = [];

        foreach ($commandes as $commande) {
            foreach ($commande->getLignes() as $ligne) {
                $nombreArticles += $ligne->getQuantite();
                $libelle = $ligne->getLibelleComplet();
                $categorie = $ligne->getProduit()->getCategorie();
                $nomCategorie = $categorie?->getNom() ?? 'Sans catégorie';

                $totauxParCategorie[$nomCategorie][$libelle] =
                    ($totauxParCategorie[$nomCategorie][$libelle] ?? 0) + $ligne->getQuantite();
            }
        }

        // Tri des produits par quantité décroissante à l'intérieur de chaque catégorie.
        foreach ($totauxParCategorie as &$produitsDeLaCategorie) {
            arsort($produitsDeLaCategorie);
        }
        unset($produitsDeLaCategorie);
        ksort($totauxParCategorie);

        return $this->render('admin/commande/index.html.twig', [
            'jours' => $jourRetraitRepository->trouverTriesParJourSemaine(),
            'dateChoisie' => $dateChoisie,
            'datePrecedente' => $datePrecedente,
            'dateSuivante' => $dateSuivante,
            'commandes' => $commandes,
            'nombreArticles' => $nombreArticles,
            'totauxParCategorie' => $totauxParCategorie,
        ]);
    }
}
