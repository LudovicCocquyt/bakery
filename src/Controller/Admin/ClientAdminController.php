<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Commande;
use App\Entity\Configuration;
use App\Entity\LigneCommande;
use App\Entity\MouvementSolde;
use App\Entity\Produit;
use App\Entity\Remise;
use App\Repository\ClientRepository;
use App\Repository\ConfigurationRepository;
use App\Repository\ProduitRepository;
use App\Repository\VarianteProduitRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/clients')]
#[IsGranted('ROLE_ADMIN')]
class ClientAdminController extends AbstractController
{
    #[Route('', name: 'admin_clients_index', methods: ['GET'])]
    public function index(Request $request, ClientRepository $clientRepository): Response
    {
        $terme = trim((string) $request->query->get('q', ''));

        $clients = '' !== $terme
            ? $clientRepository->rechercher($terme)
            : $clientRepository->trouverTousTries();

        return $this->render('admin/client/index.html.twig', [
            'clients' => $clients,
            'terme' => $terme,
        ]);
    }

    /**
     * Endpoint JSON utilisé par la recherche instantanée (JS) de la liste
     * clients : recherche par nom, email ou téléphone, à chaque lettre tapée.
     */
    #[Route('/recherche.json', name: 'admin_clients_recherche_json', methods: ['GET'])]
    public function rechercheJson(Request $request, ClientRepository $clientRepository): JsonResponse
    {
        $terme = trim((string) $request->query->get('q', ''));

        $clients = '' !== $terme ? $clientRepository->rechercher($terme) : $clientRepository->trouverTousTries();

        return new JsonResponse(array_map(fn (Client $c) => [
            'id' => $c->getId(),
            'nomPrenom' => $c->getNomPrenom(),
            'email' => $c->getEmail(),
            'telephone' => $c->getTelephone(),
            'url' => $this->generateUrl('admin_clients_fiche', ['id' => $c->getId()]),
        ], $clients));
    }

    #[Route('/nouveau', name: 'admin_clients_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(Request $request, EntityManagerInterface $em): Response
    {
        $client = new Client();

        if ($request->isMethod('POST')) {
            return $this->enregistrerClient($client, $request, $em, true);
        }

        return $this->render('admin/client/form.html.twig', ['client' => $client]);
    }

    #[Route('/{id}/modifier', name: 'admin_clients_modifier', methods: ['GET', 'POST'])]
    public function modifier(Client $client, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            return $this->enregistrerClient($client, $request, $em, false);
        }

        return $this->render('admin/client/form.html.twig', ['client' => $client]);
    }

    private function enregistrerClient(Client $client, Request $request, EntityManagerInterface $em, bool $nouveau): Response
    {
        if (!$this->isCsrfTokenValid('client_'.($client->getId() ?? 'nouveau'), (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('erreur', 'Formulaire expiré, réessaie.');

            return $this->redirectToRoute('admin_clients_index');
        }

        $nomPrenom = trim((string) $request->request->get('nom_prenom'));
        $email = trim((string) $request->request->get('email'));
        $telephone = trim((string) $request->request->get('telephone')) ?: null;

        if ('' === $nomPrenom || '' === $email) {
            $this->addFlash('erreur', 'Le nom & prénom et l\'email sont obligatoires.');

            return $this->render('admin/client/form.html.twig', ['client' => $client]);
        }

        $client->setNomPrenom($nomPrenom);
        $client->setEmail($email);
        $client->setTelephone($telephone);

        try {
            if ($nouveau) {
                $em->persist($client);
            }
            $em->flush();
        } catch (UniqueConstraintViolationException) {
            $this->addFlash('erreur', 'Un client existe déjà avec ce nom, cet email ou ce téléphone.');

            return $this->render('admin/client/form.html.twig', ['client' => $client]);
        }

        $this->addFlash('succes', $nouveau ? 'Fiche client créée.' : 'Fiche client mise à jour.');

        return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'admin_clients_supprimer', methods: ['POST'])]
    public function supprimer(Client $client, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('supprimer_client_'.$client->getId(), (string) $request->request->get('_csrf_token'))) {
            // Les commandes existantes ne sont pas supprimées : elles
            // repassent simplement sans fiche client rattachée.
            foreach ($client->getCommandes() as $commande) {
                $commande->setClient(null);
            }

            $em->remove($client);
            $em->flush();
            $this->addFlash('succes', 'Fiche client supprimée.');

            return $this->redirectToRoute('admin_clients_index');
        }

        return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
    }

    #[Route('/{id}', name: 'admin_clients_fiche', methods: ['GET'])]
    public function fiche(Client $client, Request $request, ConfigurationRepository $configurationRepository): Response
    {
        $configuration = $configurationRepository->getOuCreer();

        $parPage = 15;
        $historiqueComplet = $client->getHistorique();
        $totalPages = max(1, (int) ceil(\count($historiqueComplet) / $parPage));
        $page = max(1, min($totalPages, (int) $request->query->get('page', 1)));
        $historique = \array_slice($historiqueComplet, ($page - 1) * $parPage, $parPage);

        return $this->render('admin/client/fiche.html.twig', [
            'client' => $client,
            'historique' => $historique,
            'page' => $page,
            'totalPages' => $totalPages,
            'configuration' => $configuration,
            'progressionFidelite' => $this->calculerProgressionFidelite($client, $configuration),
        ]);
    }

    /**
     * Calcule où en est le client par rapport au seuil de fidélité, et si
     * une remise est actuellement due. Une fois qu'une vraie remise
     * fidélité a été donnée pour le palier atteint, la carte ne se
     * réaffiche que lorsqu'un nouveau palier est franchi.
     */
    private function calculerProgressionFidelite(Client $client, Configuration $configuration): ?array
    {
        $mode = $configuration->getFideliteMode();
        $seuil = $configuration->getFideliteSeuil();

        if (Configuration::FIDELITE_AUCUN === $mode || null === $seuil || $seuil <= 0) {
            return null;
        }

        $commandes = $client->getCommandes();

        $compteur = match ($mode) {
            Configuration::FIDELITE_PASSAGES => \count($commandes),
            Configuration::FIDELITE_ARTICLES => array_sum(array_map(
                fn (Commande $c) => array_sum(array_map(fn ($l) => $l->getQuantite(), $c->getLignes()->toArray())),
                $commandes->toArray()
            )),
            Configuration::FIDELITE_EUROS => array_sum(array_map(
                fn (Commande $c) => (float) ($c->getMontantPaye() ?? '0'),
                $commandes->toArray()
            )),
            default => 0,
        };

        $paliersAtteints = intdiv((int) $compteur, $seuil);
        $nombreRemisesFidelite = \count(array_filter(
            $client->getRemises()->toArray(),
            fn (Remise $r) => $r->isEstFidelite()
        ));
        $aDroitARemise = $paliersAtteints > 0 && $paliersAtteints > $nombreRemisesFidelite;

        // Une fois la remise fidélité du palier actuel donnée, la carte
        // disparaît jusqu'au prochain palier franchi.
        if (!$aDroitARemise) {
            return null;
        }

        return [
            'mode' => $mode,
            'compteur' => $compteur,
            'seuil' => $seuil,
            'aDroitARemise' => true,
        ];
    }

    #[Route('/{id}/remise', name: 'admin_clients_ajouter_remise', methods: ['POST'])]
    public function ajouterRemise(Client $client, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('ajouter_remise_'.$client->getId(), (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('erreur', 'Formulaire expiré, réessaie.');

            return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
        }

        $nom = trim((string) $request->request->get('nom'));
        $montant = (string) $request->request->get('montant', '0');

        if ('' === $nom) {
            $this->addFlash('erreur', 'Le nom de la remise est obligatoire.');

            return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
        }

        $remise = new Remise();
        $remise->setClient($client);
        $remise->setNom($nom);
        $remise->setMontant($montant);

        $em->persist($remise);
        $em->flush();

        $this->addFlash('succes', 'Remise ajoutée.');

        return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
    }

    /**
     * Valide la remise fidélité proposée par la carte "Fidélité" — distincte
     * de ajouterRemise() car marquée estFidelite : c'est cette marque qui
     * permet de savoir que le palier actuel a bien été honoré, et donc de
     * faire disparaître la carte jusqu'au prochain palier.
     */
    #[Route('/{id}/remise-fidelite', name: 'admin_clients_ajouter_remise_fidelite', methods: ['POST'])]
    public function ajouterRemiseFidelite(Client $client, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('remise_fidelite_'.$client->getId(), (string) $request->request->get('_csrf_token'))) {
            $montant = (string) $request->request->get('montant', '0');

            $remise = new Remise();
            $remise->setClient($client);
            $remise->setNom('Remise fidélité');
            $remise->setMontant($montant);
            $remise->setEstFidelite(true);

            $em->persist($remise);
            $em->flush();

            $this->addFlash('succes', 'Remise fidélité ajoutée.');
        }

        return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
    }

    /**
     * Seule action manuelle possible sur le solde : un dépôt (positif).
     * Le solde ne peut jamais être diminué directement — il ne baisse que
     * lorsqu'une commande est réglée "par solde" (voir marquerPaye).
     * Chaque dépôt crée une ligne dans l'historique.
     */
    #[Route('/{id}/solde/ajouter', name: 'admin_clients_solde_ajouter', methods: ['POST'])]
    public function ajouterSolde(Client $client, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('solde_'.$client->getId(), (string) $request->request->get('_csrf_token'))) {
            $montant = (string) $request->request->get('montant', '0');

            $client->setSolde(bcadd($client->getSolde(), $montant, 2));

            $mouvement = new MouvementSolde();
            $mouvement->setClient($client);
            $mouvement->setMontant($montant);
            $mouvement->setMotif('Ajout crédit');
            $em->persist($mouvement);

            $em->flush();
            $this->addFlash('succes', sprintf('%s € ajouté(s) au solde.', $montant));
        }

        return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
    }

    /**
     * Nouvelle commande : toujours en saisie simplifiée (nombre d'articles
     * uniquement, sans choisir les produits) — c'est une vente sur place,
     * pas une commande en ligne détaillée.
     */
    #[Route('/{id}/commande/nouvelle', name: 'admin_clients_commande_nouvelle', methods: ['GET', 'POST'])]
    public function nouvelleCommande(Client $client, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            return $this->enregistrerCommandeManuelle(null, $client, $request, $em);
        }

        return $this->render('admin/client/commande_form.html.twig', [
            'client' => $client,
            'commande' => null,
        ]);
    }

    /**
     * Modifier une commande existante : impossible une fois payée. Le
     * formulaire dépend du type de commande — vente sur place (simplifiée,
     * juste un nombre d'articles) ou commande en ligne (détail des
     * produits, on peut en ajouter/enlever).
     */
    #[Route('/{clientId}/commande/{id}/modifier', name: 'admin_clients_commande_modifier', methods: ['GET', 'POST'])]
    public function modifierCommande(
        int $clientId,
        Commande $commande,
        Request $request,
        EntityManagerInterface $em,
        ProduitRepository $produitRepository,
        VarianteProduitRepository $varianteProduitRepository,
    ): Response {
        $client = $commande->getClient();

        if (null === $client || $client->getId() !== $clientId) {
            throw $this->createNotFoundException('Commande introuvable pour ce client.');
        }

        if ($commande->estPayee()) {
            $this->addFlash('erreur', 'Cette commande est payée, elle ne peut plus être modifiée.');

            return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
        }

        // Vente sur place (saisie simplifiée) : formulaire nombre d'articles.
        if ($commande->estSaisieSimplifiee() || $commande->getLignes()->isEmpty()) {
            if ($request->isMethod('POST')) {
                return $this->enregistrerCommandeManuelle($commande, $client, $request, $em);
            }

            return $this->render('admin/client/commande_form.html.twig', [
                'client' => $client,
                'commande' => $commande,
            ]);
        }

        // Commande en ligne : formulaire détaillé, ajout/retrait de produits.
        if ($request->isMethod('POST')) {
            return $this->enregistrerDetailCommande($commande, $client, $request, $em, $varianteProduitRepository);
        }

        return $this->render('admin/client/commande_form_detail.html.twig', [
            'client' => $client,
            'commande' => $commande,
            'produits' => $produitRepository->findAll(),
        ]);
    }

    /**
     * Enregistre une commande saisie manuellement (ou sa modification), en
     * version simplifiée : juste un nombre d'articles et, optionnellement,
     * le prix déjà payé — pas besoin de détailler chaque produit. Si le
     * prix n'est pas renseigné ici, il reste à saisir plus tard via
     * "Marquer payé", comme pour une commande en ligne classique.
     */
    private function enregistrerCommandeManuelle(?Commande $commande, Client $client, Request $request, EntityManagerInterface $em): Response
    {
        $csrfId = $commande ? 'commande_'.$commande->getId() : 'commande_nouvelle_'.$client->getId();
        if (!$this->isCsrfTokenValid($csrfId, (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('erreur', 'Formulaire expiré, réessaie.');

            return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
        }

        $dateRetraitBrute = (string) $request->request->get('date_retrait');
        try {
            $dateRetrait = new \DateTimeImmutable($dateRetraitBrute ?: 'today');
        } catch (\Exception) {
            $dateRetrait = new \DateTimeImmutable('today');
        }

        $nombreArticles = (int) $request->request->get('nombre_articles', 0);
        $prixAPayer = trim((string) $request->request->get('prix_a_payer'));

        if ($nombreArticles <= 0) {
            $this->addFlash('erreur', 'Indique un nombre d\'articles supérieur à 0.');

            return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
        }

        $nouveau = null === $commande;

        if ($nouveau) {
            $commande = new Commande();
            $commande->setClient($client);
            $commande->setNomClient($client->getNomPrenom());
            $commande->setEmailClient($client->getEmail());
            $commande->setTelephoneClient($client->getTelephone());
            $commande->setStatut(Commande::STATUT_CONFIRMEE);
        }

        $commande->setDateRetrait($dateRetrait);
        $commande->setNombreArticlesManuel($nombreArticles);

        if ('' !== $prixAPayer) {
            $commande->setMontantPaye($prixAPayer);
        }

        if ($nouveau) {
            $em->persist($commande);
        }

        $em->flush();

        $this->addFlash('succes', $nouveau ? 'Vente directe ajoutée à la fiche.' : 'Vente directe mise à jour.');

        return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
    }

    /**
     * Enregistre la modification du détail d'une commande en ligne :
     * remplace intégralement les lignes existantes par celles soumises,
     * pour permettre d'ajouter ou d'enlever des produits librement.
     * lignes[N][option] = "produit:12" ou "variante:5", lignes[N][quantite] = 2
     */
    private function enregistrerDetailCommande(
        Commande $commande,
        Client $client,
        Request $request,
        EntityManagerInterface $em,
        VarianteProduitRepository $varianteProduitRepository,
    ): Response {
        if (!$this->isCsrfTokenValid('commande_detail_'.$commande->getId(), (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('erreur', 'Formulaire expiré, réessaie.');

            return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
        }

        if ($commande->estPayee()) {
            $this->addFlash('erreur', 'Cette commande est payée, elle ne peut plus être modifiée.');

            return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
        }

        foreach ($commande->getLignes()->toArray() as $ligne) {
            $em->remove($ligne);
        }
        $commande->getLignes()->clear();

        $auMoinsUneLigne = false;

        foreach ($request->request->all('lignes') as $donnees) {
            $option = (string) ($donnees['option'] ?? '');
            $quantite = (int) ($donnees['quantite'] ?? 0);
            // dump($request);
            // dump($request->request->all('lignes'));
            // dd($quantite);

            // if ('' === $option || $quantite <= 0) {
            //     continue;
            // }

            [$type, $id] = array_pad(explode(':', $option, 2), 2, null);
            $id = (int) $id;

            $ligne = new LigneCommande();
            $ligne->setQuantite($quantite);

            if ('variante' === $type) {
                $variante = $varianteProduitRepository->find($id);
                if (null === $variante) {
                    continue;
                }
                $ligne->setProduit($variante->getProduit());
                $ligne->setVarianteProduit($variante);
                $ligne->setPrixUnitaire($variante->getProduit()->getPrix() ?? '0');
            } elseif ('produit' === $type) {
                $produit = $em->getRepository(Produit::class)->find($id);
                if (null === $produit) {
                    continue;
                }
                $ligne->setProduit($produit);
                $ligne->setPrixUnitaire($produit->getPrix() ?? '0');
            } else {
                continue;
            }

            $commande->ajouterLigne($ligne);
            $em->persist($ligne);
            $auMoinsUneLigne = true;
        }

        if (!$auMoinsUneLigne) {
            $this->addFlash('erreur', 'Ajoute au moins un article à la commande.');

            return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
        }

        $em->flush();

        $this->addFlash('succes', 'Commande mise à jour.');

        return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
    }

    #[Route('/{clientId}/commande/{id}/supprimer', name: 'admin_clients_commande_supprimer', methods: ['POST'])]
    public function supprimerCommande(int $clientId, Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        $client = $commande->getClient();

        if (null === $client || $client->getId() !== $clientId) {
            throw $this->createNotFoundException('Commande introuvable pour ce client.');
        }

        if ($commande->estPayee()) {
            $this->addFlash('erreur', 'Cette commande est payée, elle ne peut plus être supprimée.');

            return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
        }

        if ($this->isCsrfTokenValid('supprimer_commande_'.$commande->getId(), (string) $request->request->get('_csrf_token'))) {
            $em->remove($commande);
            $em->flush();
            $this->addFlash('succes', 'Commande supprimée.');
        }

        return $this->redirectToRoute('admin_clients_fiche', ['id' => $client->getId()]);
    }
}
