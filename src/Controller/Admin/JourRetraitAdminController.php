<?php

namespace App\Controller\Admin;

use App\Entity\JourRetrait;
use App\Repository\JourRetraitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/jours-retrait')]
#[IsGranted('ROLE_ADMIN')]
class JourRetraitAdminController extends AbstractController
{
    #[Route('', name: 'admin_jours_index', methods: ['GET'])]
    public function index(JourRetraitRepository $jourRetraitRepository): Response
    {
        $joursExistants = array_map(fn (JourRetrait $j) => $j->getJour(), $jourRetraitRepository->findAll());
        $joursDisponibles = array_diff(JourRetrait::JOURS_VALIDES, $joursExistants);

        return $this->render('admin/jour_retrait/index.html.twig', [
            'jours' => $jourRetraitRepository->findAll(),
            'joursDisponibles' => $joursDisponibles,
        ]);
    }

    #[Route('/ajouter', name: 'admin_jours_ajouter', methods: ['POST'])]
    public function ajouter(Request $request, EntityManagerInterface $em, JourRetraitRepository $jourRetraitRepository): Response
    {
        if (!$this->isCsrfTokenValid('ajouter_jour', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('erreur', 'Formulaire expiré, réessaie.');

            return $this->redirectToRoute('admin_jours_index');
        }

        $nomJour = strtolower((string) $request->request->get('jour'));

        if (!\in_array($nomJour, JourRetrait::JOURS_VALIDES, true)) {
            $this->addFlash('erreur', 'Jour invalide.');

            return $this->redirectToRoute('admin_jours_index');
        }

        if (null !== $jourRetraitRepository->trouverParNomJour($nomJour)) {
            $this->addFlash('erreur', 'Ce jour est déjà configuré.');

            return $this->redirectToRoute('admin_jours_index');
        }

        $jourRetrait = new JourRetrait();
        $jourRetrait->setJour($nomJour);
        $jourRetrait->setActif(true);

        $em->persist($jourRetrait);
        $em->flush();

        $this->addFlash('succes', sprintf('Jour "%s" ajouté.', $nomJour));

        return $this->redirectToRoute('admin_jours_index');
    }

    #[Route('/{id}/basculer', name: 'admin_jours_basculer', methods: ['POST'])]
    public function basculer(JourRetrait $jourRetrait, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('basculer_jour_'.$jourRetrait->getId(), (string) $request->request->get('_csrf_token'))) {
            $jourRetrait->setActif(!$jourRetrait->isActif());
            $em->flush();

            $this->addFlash('succes', sprintf(
                'Jour "%s" %s.',
                $jourRetrait->getJour(),
                $jourRetrait->isActif() ? 'activé' : 'désactivé'
            ));
        }

        return $this->redirectToRoute('admin_jours_index');
    }
}
