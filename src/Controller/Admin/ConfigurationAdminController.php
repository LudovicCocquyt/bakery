<?php

namespace App\Controller\Admin;

use App\Entity\Configuration;
use App\Repository\ConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/configuration')]
#[IsGranted('ROLE_ADMIN')]
class ConfigurationAdminController extends AbstractController
{
    #[Route('', name: 'admin_configuration_index', methods: ['GET'])]
    public function index(ConfigurationRepository $configurationRepository): Response
    {
        return $this->render('admin/configuration/index.html.twig', [
            'configuration' => $configurationRepository->getOuCreer(),
        ]);
    }

    #[Route('/enregistrer', name: 'admin_configuration_enregistrer', methods: ['POST'])]
    public function enregistrer(Request $request, ConfigurationRepository $configurationRepository, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('configuration', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('erreur', 'Formulaire expiré, réessaie.');

            return $this->redirectToRoute('admin_configuration_index');
        }

        $configuration = $configurationRepository->getOuCreer();

        $configuration->setFicheClientActivee((bool) $request->request->get('fiche_client_activee'));
        $configuration->setNomSite((string) $request->request->get('nom_site', $configuration->getNomSite()));
        $urlRetour = trim((string) $request->request->get('url_retour_site'));
        $configuration->setUrlRetourSite('' !== $urlRetour ? $urlRetour : null);
        $configuration->setNomBoutonRetour((string) $request->request->get('nom_bouton_retour', $configuration->getNomBoutonRetour()));
        $configuration->setSoldeClientActivee((bool) $request->request->get('solde_client_activee'));

        $fideliteMode = (string) $request->request->get('fidelite_mode', Configuration::FIDELITE_AUCUN);
        if (\in_array($fideliteMode, Configuration::FIDELITE_MODES_VALIDES, true)) {
            $configuration->setFideliteMode($fideliteMode);
        }

        $seuil = $request->request->get('fidelite_seuil');
        $configuration->setFideliteSeuil('' !== $seuil && null !== $seuil ? (int) $seuil : null);

        $configuration->setPoliceTexte((string) $request->request->get('police_texte', $configuration->getPoliceTexte()));
        $configuration->setTailleTexteBase((int) $request->request->get('taille_texte_base', $configuration->getTailleTexteBase()));
        $configuration->setTailleTitre1((int) $request->request->get('taille_titre1', $configuration->getTailleTitre1()));
        $configuration->setTailleTitre2((int) $request->request->get('taille_titre2', $configuration->getTailleTitre2()));
        $configuration->setCouleurPrincipale((string) $request->request->get('couleur_principale', $configuration->getCouleurPrincipale()));
        $configuration->setCouleurTexte((string) $request->request->get('couleur_texte', $configuration->getCouleurTexte()));
        $configuration->setCouleurFond((string) $request->request->get('couleur_fond', $configuration->getCouleurFond()));
        $configuration->setCouleurBordure((string) $request->request->get('couleur_bordure', $configuration->getCouleurBordure()));

        $em->flush();

        $this->addFlash('succes', 'Configuration enregistrée.');

        return $this->redirectToRoute('admin_configuration_index');
    }
}
