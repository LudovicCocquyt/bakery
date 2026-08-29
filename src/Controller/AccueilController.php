<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur séparé (sans préfixe de route) pour la racine du site : il ne
 * peut PAS être une méthode de CommandeController, car celui-ci a un préfixe
 * de classe #[Route('/commande')] qui transformerait "/" en "/commande/".
 */
class AccueilController extends AbstractController
{
    #[Route('/', name: 'accueil', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('commande_index');
    }
}
