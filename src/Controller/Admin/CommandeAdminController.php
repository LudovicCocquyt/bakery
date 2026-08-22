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
        $jours = $jourRetraitRepository->findAll();

        // Jour choisi via ?jour=samedi, sinon le premier jour actif par défaut.
        $nomJourChoisi = $request->query->get('jour');
        $jourChoisi = $nomJourChoisi
            ? $jourRetraitRepository->trouverParNomJour($nomJourChoisi)
            : ($jourRetraitRepository->trouverActifs()[0] ?? null);

        $commandes = $jourChoisi ? $commandeRepository->trouverParJour($jourChoisi) : [];

        $total = '0.00';
        $nombreArticles = 0;
        foreach ($commandes as $commande) {
            $total = bcadd($total, $commande->getTotal(), 2);
            foreach ($commande->getLignes() as $ligne) {
                $nombreArticles += $ligne->getQuantite();
            }
        }

        return $this->render('admin/commande/index.html.twig', [
            'jours' => $jours,
            'jourChoisi' => $jourChoisi,
            'commandes' => $commandes,
            'total' => $total,
            'nombreArticles' => $nombreArticles,
        ]);
    }
}
