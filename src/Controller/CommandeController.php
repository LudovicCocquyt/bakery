<?php

namespace App\Controller;

use App\Entity\VarianteProduit;
use App\Repository\CategorieRepository;
use App\Repository\CommandeRepository;
use App\Repository\JourRetraitRepository;
use App\Repository\ProduitRepository;
use App\Repository\VarianteProduitRepository;
use App\Service\CommandeService;
use App\Service\JourRetraitInactifException;
use App\Service\LigneDemandee;
use App\Service\StockInsuffisantException;
use App\Service\VarianteInvalideException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

#[Route('/commande')]
class CommandeController extends AbstractController
{
    #[Route('', name: 'commande_index', methods: ['GET'])]
    public function index(JourRetraitRepository $jourRetraitRepository, CommandeService $commandeService): Response
    {
        // Pour chaque jour actif, on calcule la vraie prochaine date
        // disponible (en tenant compte de la coupure de réservation), pour
        // l'afficher au client (ex: "Jeudi 18 oct").
        $joursAvecDate = array_map(
            fn ($jour) => [
                'jourRetrait' => $jour,
                'date' => $commandeService->prochaineDateDisponible($jour->getJour()),
            ],
            $jourRetraitRepository->trouverActifs()
        );

        return $this->render('commande/index.html.twig', [
            'joursAvecDate' => $joursAvecDate,
        ]);
    }

    #[Route('/{jour}', name: 'commande_commander', methods: ['GET'])]
    public function commander(
        string $jour,
        JourRetraitRepository $jourRetraitRepository,
        ProduitRepository $produitRepository,
        CategorieRepository $categorieRepository,
        CommandeService $commandeService,
    ): Response {
        $jourRetrait = $jourRetraitRepository->trouverParNomJour($jour);

        if (null === $jourRetrait || !$jourRetrait->isActif()) {
            throw $this->createNotFoundException('Ce jour de retrait n\'est pas disponible.');
        }

        $produitsPiece = array_filter(
            $produitRepository->trouverActifs(),
            fn ($produit) => !$produit->estVenduAuKilo() && null !== $produit->getStockPourJour($jourRetrait)
        );

        $produitsKilo = array_filter(
            $produitRepository->trouverActifs(),
            function ($produit) use ($jourRetrait) {
                if (!$produit->estVenduAuKilo()) {
                    return false;
                }
                foreach ($produit->getVariantes() as $variante) {
                    if (null !== $variante->getStockPourJour($jourRetrait)) {
                        return true;
                    }
                }

                return false;
            }
        );

        // Regroupement par catégorie (triée par ordre d'affichage), avec un
        // groupe "Sans catégorie" à la fin pour les produits non classés.
        $groupes = [];
        foreach ($categorieRepository->trouverTriees() as $categorie) {
            $piece = array_filter($produitsPiece, fn ($p) => $p->getCategorie() === $categorie);
            $kilo = array_filter($produitsKilo, fn ($p) => $p->getCategorie() === $categorie);
            if ([] !== $piece || [] !== $kilo) {
                $groupes[] = ['nom' => $categorie->getNom(), 'produitsPiece' => $piece, 'produitsKilo' => $kilo];
            }
        }
        $piecesSansCategorie = array_filter($produitsPiece, fn ($p) => null === $p->getCategorie());
        $kilosSansCategorie = array_filter($produitsKilo, fn ($p) => null === $p->getCategorie());
        if ([] !== $piecesSansCategorie || [] !== $kilosSansCategorie) {
            $groupes[] = ['nom' => null, 'produitsPiece' => $piecesSansCategorie, 'produitsKilo' => $kilosSansCategorie];
        }

        return $this->render('commande/commander.html.twig', [
            'jourRetrait' => $jourRetrait,
            'dateRetrait' => $commandeService->prochaineDateDisponible($jourRetrait->getJour()),
            'groupes' => $groupes,
        ]);
    }

    #[Route('/{jour}', name: 'commande_traiter', methods: ['POST'])]
    public function traiter(
        string $jour,
        Request $request,
        JourRetraitRepository $jourRetraitRepository,
        ProduitRepository $produitRepository,
        VarianteProduitRepository $varianteProduitRepository,
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

        $lignesDemandees = [];

        foreach ($request->request->all('quantites') as $produitId => $quantite) {
            $quantite = (int) $quantite;
            if ($quantite <= 0) {
                continue;
            }
            $produit = $produitRepository->find((int) $produitId);
            if (null === $produit || !$produit->isActif() || $produit->estVenduAuKilo()) {
                continue;
            }
            $lignesDemandees[] = new LigneDemandee($produit, $quantite);
        }

        foreach ($request->request->all('quantitesVariantes') as $varianteId => $quantite) {
            $quantite = (int) $quantite;
            if ($quantite <= 0) {
                continue;
            }
            /** @var VarianteProduit|null $variante */
            $variante = $varianteProduitRepository->find((int) $varianteId);
            if (null === $variante || !$variante->getProduit()->isActif()) {
                continue;
            }
            $lignesDemandees[] = new LigneDemandee($variante->getProduit(), $quantite, $variante);
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
        } catch (VarianteInvalideException $e) {
            $this->addFlash('erreur', $e->getMessage());

            return $this->redirectToRoute('commande_commander', ['jour' => $jour]);
        } catch (StockInsuffisantException $e) {
            $this->addFlash('erreur', sprintf(
                'Stock insuffisant : il ne reste que %d en stock.',
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
