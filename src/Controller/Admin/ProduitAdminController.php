<?php

namespace App\Controller\Admin;

use App\Entity\Produit;
use App\Entity\StockProduit;
use App\Repository\JourRetraitRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/produits')]
#[IsGranted('ROLE_ADMIN')]
class ProduitAdminController extends AbstractController
{
    #[Route('', name: 'admin_produits_index', methods: ['GET'])]
    public function index(ProduitRepository $produitRepository): Response
    {
        return $this->render('admin/produit/index.html.twig', [
            'produits' => $produitRepository->findAll(),
        ]);
    }

    #[Route('/nouveau', name: 'admin_produits_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(Request $request, EntityManagerInterface $em, JourRetraitRepository $jourRetraitRepository): Response
    {
        $produit = new Produit();

        if ($request->isMethod('POST')) {
            $this->hydrater($produit, $request);
            $em->persist($produit);
            $this->synchroniserStocks($produit, $request, $jourRetraitRepository, $em);
            $em->flush();

            $this->addFlash('succes', 'Produit créé.');

            return $this->redirectToRoute('admin_produits_index');
        }

        return $this->render('admin/produit/form.html.twig', [
            'produit' => $produit,
            'jours' => $jourRetraitRepository->findAll(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_produits_modifier', methods: ['GET', 'POST'])]
    public function modifier(Produit $produit, Request $request, EntityManagerInterface $em, JourRetraitRepository $jourRetraitRepository): Response
    {
        if ($request->isMethod('POST')) {
            $this->hydrater($produit, $request);
            $this->synchroniserStocks($produit, $request, $jourRetraitRepository, $em);
            $em->flush();

            $this->addFlash('succes', 'Produit mis à jour.');

            return $this->redirectToRoute('admin_produits_index');
        }

        return $this->render('admin/produit/form.html.twig', [
            'produit' => $produit,
            'jours' => $jourRetraitRepository->findAll(),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_produits_supprimer', methods: ['POST'])]
    public function supprimer(Produit $produit, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('supprimer_produit_'.$produit->getId(), (string) $request->request->get('_csrf_token'))) {
            $em->remove($produit);
            $em->flush();
            $this->addFlash('succes', 'Produit supprimé.');
        }

        return $this->redirectToRoute('admin_produits_index');
    }

    private function hydrater(Produit $produit, Request $request): void
    {
        $produit->setNom((string) $request->request->get('nom'));
        $produit->setDescription((string) $request->request->get('description') ?: null);
        $produit->setPrix((string) $request->request->get('prix', '0'));
        $produit->setActif((bool) $request->request->get('actif'));
    }

    /**
     * Crée ou met à jour les lignes StockProduit à partir des champs
     * stocks[jourId] envoyés par le formulaire (un champ stock_initial par
     * jour de retrait existant). Ne touche pas au stock_restant d'un stock
     * déjà existant, pour ne pas fausser une semaine en cours — voir
     * StockProduit::setStockInitial().
     */
    private function synchroniserStocks(Produit $produit, Request $request, JourRetraitRepository $jourRetraitRepository, EntityManagerInterface $em): void
    {
        $stocksSoumis = $request->request->all('stocks');

        foreach ($jourRetraitRepository->findAll() as $jourRetrait) {
            $stockInitial = isset($stocksSoumis[$jourRetrait->getId()]) ? (int) $stocksSoumis[$jourRetrait->getId()] : null;

            if (null === $stockInitial) {
                continue;
            }

            $stock = $produit->getStockPourJour($jourRetrait);

            if (null === $stock) {
                if ($stockInitial <= 0) {
                    continue;
                }
                // Nouveau produit ou nouveau jour : le stock restant démarre au niveau initial.
                $stock = new StockProduit();
                $stock->setProduit($produit);
                $stock->setJourRetrait($jourRetrait);
                $stock->setStockInitial($stockInitial);
                $stock->setStockRestant($stockInitial);
                $em->persist($stock);
            } else {
                $stock->setStockInitial($stockInitial);
            }
        }
    }
}
