<?php

namespace App\Controller\Admin;

use App\Entity\MouvementSolde;
use App\Repository\CommandeRepository;
use App\Repository\ConfigurationRepository;
use App\Repository\JourRetraitRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        ConfigurationRepository $configurationRepository,
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

        // Séparation commandes en ligne (détail produit par produit) vs
        // ventes directes (saisie simplifiée, juste un nombre d'articles).
        $commandesEnLigne = [];
        $ventesDirectes = [];
        foreach ($commandes as $commande) {
            if ($commande->estSaisieSimplifiee()) {
                $ventesDirectes[] = $commande;
            } else {
                $commandesEnLigne[] = $commande;
            }
        }

        $nombreArticlesVentesDirectes = array_sum(array_map(
            fn ($c) => $c->getNombreArticlesManuel() ?? 0,
            $ventesDirectes
        ));

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
            'commandesEnLigne' => $commandesEnLigne,
            'ventesDirectes' => $ventesDirectes,
            'nombreArticlesVentesDirectes' => $nombreArticlesVentesDirectes,
            'nombreArticles' => $nombreArticles,
            'totauxParCategorie' => $totauxParCategorie,
            'configuration' => $configurationRepository->getOuCreer(),
        ]);
    }

    /**
     * Marque une commande comme payée. Le mode de paiement "solde" utilise
     * le solde signé du client, que le mode choisi soit "solde" (utilise
     * la réserve positive) ou "dette" (enregistre explicitement que ce
     * paiement est différé) — les deux diminuent mécaniquement le même
     * solde, seul le libellé dans l'historique diffère selon le choix du
     * boulanger. Chaque paiement par solde/dette crée une ligne d'historique.
     */
    #[Route('/{id}/marquer-paye', name: 'admin_commandes_marquer_paye', methods: ['POST'])]
    public function marquerPaye(
        int $id,
        Request $request,
        CommandeRepository $commandeRepository,
        ConfigurationRepository $configurationRepository,
        EntityManagerInterface $em,
    ): Response {
        $commande = $commandeRepository->find($id);

        if (null === $commande) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        if ($this->isCsrfTokenValid('marquer_paye_'.$commande->getId(), (string) $request->request->get('_csrf_token'))) {
            $montant = (string) $request->request->get('montant', '0');
            $mode = (string) $request->request->get('mode_paiement', 'especes');
            $client = $commande->getClient();
            $configuration = $configurationRepository->getOuCreer();

            if (\in_array($mode, ['solde', 'dette'], true)) {
                if (null === $client || !$configuration->isSoldeClientActivee()) {
                    $this->addFlash('erreur', 'Ce mode de paiement est indisponible pour cette commande.');

                    return $this->rediriger($request);
                }

                $client->setSolde(bcsub($client->getSolde(), $montant, 2));

                $mouvement = new MouvementSolde();
                $mouvement->setClient($client);
                $mouvement->setMontant('-'.$montant);
                $mouvement->setMotif(sprintf(
                    '%s (commande #%d)',
                    'dette' === $mode ? 'Ajout de dette' : 'Paiement commande',
                    $commande->getId()
                ));
                $em->persist($mouvement);
            }

            $commande->setMontantPaye($montant);
            $commande->setModePaiement($mode);
            $em->flush();
            $this->addFlash('succes', sprintf('Commande marquée payée (%s €).', $montant));
        }

        return $this->rediriger($request);
    }

    private function rediriger(Request $request): Response
    {
        $referer = $request->headers->get('referer');

        return $referer ? $this->redirect($referer) : $this->redirectToRoute('admin_commandes_index');
    }
}
