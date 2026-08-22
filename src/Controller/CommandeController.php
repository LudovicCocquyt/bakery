<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Repository\JourRetraitRepository;
use App\Repository\ProduitRepository;
use App\Service\CommandeService;
use App\Service\JourRetraitInactifException;
use App\Service\LigneDemandee;
use App\Service\StockInsuffisantException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

#[Route('/commande')]
class CommandeController extends AbstractController
{
    /**
     * Page d'accueil de la prise de commande : choix du jour de retrait.
     * Rappel métier : la commande peut être passée n'importe quel jour de
     * la semaine, c'est uniquement le RETRAIT qui est restreint à certains jours.
     */
    #[Route('', name: 'commande_index', methods: ['GET'])]
    public function index(JourRetraitRepository $jourRetraitRepository): Response
    {
        return $this->render('commande/index.html.twig', [
            'jours' => $jourRetraitRepository->trouverActifs(),
        ]);
    }

    #[Route('/{jour}', name: 'commande_commander', methods: ['GET'])]
    public function commander(string $jour, JourRetraitRepository $jourRetraitRepository, ProduitRepository $produitRepository): Response
    {
        $jourRetrait = $jourRetraitRepository->trouverParNomJour($jour);

        if (null === $jourRetrait || !$jourRetrait->isActif()) {
            throw $this->createNotFoundException('Ce jour de retrait n\'est pas disponible.');
        }

        // On ne propose que les produits actifs qui ont effectivement du
        // stock configuré (même à 0, pour pouvoir afficher "épuisé") pour ce jour.
        $produits = array_filter(
            $produitRepository->trouverActifs(),
            fn ($produit) => null !== $produit->getStockPourJour($jourRetrait)
        );

        return $this->render('commande/commander.html.twig', [
            'jourRetrait' => $jourRetrait,
            'produits' => $produits,
        ]);
    }

    #[Route('/{jour}', name: 'commande_traiter', methods: ['POST'])]
    public function traiter(
        string $jour,
        Request $request,
        JourRetraitRepository $jourRetraitRepository,
        ProduitRepository $produitRepository,
        CommandeService $commandeService,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $jourRetrait = $jourRetraitRepository->trouverParNomJour($jour);

        if (null === $jourRetrait) {
            throw $this->createNotFoundException('Jour de retrait inconnu.');
        }

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('commande', (string) $request->request->get('_csrf_token')))) {
            $this->addFlash('erreur', 'Le formulaire a expiré, merci de réessayer.');

            return $this->redirectToRoute('commande_commander', ['jour' => $jour]);
        }

        $nomClient = trim((string) $request->request->get('nom_client'));
        $emailClient = trim((string) $request->request->get('email_client'));
        $telephoneClient = trim((string) $request->request->get('telephone_client')) ?: null;

        if ('' === $nomClient || '' === $emailClient) {
            $this->addFlash('erreur', 'Le nom et l\'email sont obligatoires.');

            return $this->redirectToRoute('commande_commander', ['jour' => $jour]);
        }

        $quantitesDemandees = $request->request->all('quantites');

        $lignesDemandees = [];
        foreach ($quantitesDemandees as $produitId => $quantite) {
            $quantite = (int) $quantite;
            if ($quantite <= 0) {
                continue;
            }

            $produit = $produitRepository->find((int) $produitId);
            if (null === $produit || !$produit->isActif()) {
                continue;
            }

            $lignesDemandees[] = new LigneDemandee($produit, $quantite);
        }

        if ([] === $lignesDemandees) {
            $this->addFlash('erreur', 'Sélectionne au moins un produit.');

            return $this->redirectToRoute('commande_commander', ['jour' => $jour]);
        }

        try {
            $commande = $commandeService->passerCommande(
                $nomClient,
                $emailClient,
                $telephoneClient,
                $jourRetrait,
                $lignesDemandees
            );
        } catch (JourRetraitInactifException $e) {
            $this->addFlash('erreur', 'Ce jour de retrait n\'est plus disponible.');

            return $this->redirectToRoute('commande_index');
        } catch (StockInsuffisantException $e) {
            $this->addFlash('erreur', sprintf(
                'Stock insuffisant pour "%s" : il ne reste que %d en stock.',
                $e->produit->getNom(),
                $e->disponible
            ));

            return $this->redirectToRoute('commande_commander', ['jour' => $jour]);
        }

        return $this->redirectToRoute('commande_confirmation', ['id' => $commande->getId()]);
    }

    #[Route('/confirmation/{id}', name: 'commande_confirmation', methods: ['GET'])]
    public function confirmation(int $id, CommandeRepository $commandeRepository): Response
    {
        $commande = $commandeRepository->find($id);

        if (null === $commande) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        return $this->render('commande/confirmation.html.twig', [
            'commande' => $commande,
        ]);
    }
}
