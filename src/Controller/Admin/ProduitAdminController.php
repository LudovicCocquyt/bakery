<?php

namespace App\Controller\Admin;

use App\Entity\Produit;
use App\Entity\StockProduit;
use App\Entity\StockVariante;
use App\Entity\VarianteProduit;
use App\Repository\CategorieRepository;
use App\Repository\JourRetraitRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/produits')]
#[IsGranted('ROLE_ADMIN')]
class ProduitAdminController extends AbstractController
{
    private const EXTENSIONS_AUTORISEES = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('', name: 'admin_produits_index', methods: ['GET'])]
    public function index(ProduitRepository $produitRepository): Response
    {
        return $this->render('admin/produit/index.html.twig', [
            'produits' => $produitRepository->findAll(),
        ]);
    }

    #[Route('/nouveau', name: 'admin_produits_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(Request $request, EntityManagerInterface $em, JourRetraitRepository $jourRetraitRepository, CategorieRepository $categorieRepository): Response
    {
        $produit = new Produit();

        if ($request->isMethod('POST')) {
            $this->hydrater($produit, $request, $categorieRepository);
            $em->persist($produit);
            $this->synchroniser($produit, $request, $jourRetraitRepository, $em);
            $em->flush();

            $this->addFlash('succes', 'Produit créé.');

            return $this->redirectToRoute('admin_produits_index');
        }

        return $this->render('admin/produit/form.html.twig', [
            'produit' => $produit,
            'jours' => $jourRetraitRepository->findAll(),
            'categories' => $categorieRepository->trouverTriees(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_produits_modifier', methods: ['GET', 'POST'])]
    public function modifier(Produit $produit, Request $request, EntityManagerInterface $em, JourRetraitRepository $jourRetraitRepository, CategorieRepository $categorieRepository): Response
    {
        if ($request->isMethod('POST')) {
            $this->hydrater($produit, $request, $categorieRepository);
            $this->synchroniser($produit, $request, $jourRetraitRepository, $em);
            $em->flush();

            $this->addFlash('succes', 'Produit mis à jour.');

            return $this->redirectToRoute('admin_produits_index');
        }

        return $this->render('admin/produit/form.html.twig', [
            'produit' => $produit,
            'jours' => $jourRetraitRepository->findAll(),
            'categories' => $categorieRepository->trouverTriees(),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_produits_supprimer', methods: ['POST'])]
    public function supprimer(Produit $produit, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('supprimer_produit_'.$produit->getId(), (string) $request->request->get('_csrf_token'))) {
            $this->supprimerFichierImage($produit->getImage());
            $em->remove($produit);
            $em->flush();
            $this->addFlash('succes', 'Produit supprimé.');
        }

        return $this->redirectToRoute('admin_produits_index');
    }

    private function hydrater(Produit $produit, Request $request, CategorieRepository $categorieRepository): void
    {
        $produit->setNom((string) $request->request->get('nom'));
        $produit->setDescription((string) $request->request->get('description') ?: null);
        $produit->setActif((bool) $request->request->get('actif'));

        $unite = (string) $request->request->get('unite', Produit::UNITE_PIECE);
        $produit->setUnite($unite);

        // Le prix s'applique dans les deux cas : prix à la pièce, ou prix au kg.
        $produit->setPrix((string) $request->request->get('prix', '0'));

        $categorieId = $request->request->get('categorie');
        $produit->setCategorie($categorieId ? $categorieRepository->find((int) $categorieId) : null);

        $this->gererUploadImage($produit, $request);
    }

    /**
     * Gère l'upload d'une nouvelle image (champ "image", type file). Si une
     * image existait déjà, l'ancienne est supprimée du disque pour ne pas
     * accumuler de fichiers orphelins. Un champ "supprimer_image" coché
     * permet aussi de retirer l'image sans en remettre une nouvelle.
     */
    private function gererUploadImage(Produit $produit, Request $request): void
    {
        /** @var UploadedFile|null $fichier */
        $fichier = $request->files->get('image');

        if (null !== $fichier && $fichier->isValid()) {
            $extension = strtolower($fichier->getClientOriginalExtension());

            if (!\in_array($extension, self::EXTENSIONS_AUTORISEES, true)) {
                $this->addFlash('erreur', 'Format d\'image non supporté (jpg, png ou webp uniquement). Image ignorée.');

                return;
            }

            $nomFichier = bin2hex(random_bytes(16)).'.'.$extension;
            $dossier = $this->projectDir.'/public/uploads/produits';

            if (!is_dir($dossier)) {
                mkdir($dossier, 0755, true);
            }

            $fichier->move($dossier, $nomFichier);

            // On supprime l'ancienne image seulement après que la nouvelle a
            // bien été enregistrée, pour ne jamais se retrouver sans image
            // en cas d'échec du move() ci-dessus.
            $this->supprimerFichierImage($produit->getImage());

            $produit->setImage($nomFichier);

            return;
        }

        if ($request->request->get('supprimer_image')) {
            $this->supprimerFichierImage($produit->getImage());
            $produit->setImage(null);
        }
    }

    private function supprimerFichierImage(?string $nomFichier): void
    {
        if (null === $nomFichier) {
            return;
        }

        $chemin = $this->projectDir.'/public/uploads/produits/'.$nomFichier;

        if (is_file($chemin)) {
            unlink($chemin);
        }
    }

    /**
     * Selon l'unité choisie, synchronise soit le stock par jour du produit
     * lui-même (à la pièce), soit les variantes (libellé) et leur stock par
     * jour respectif (au kilo).
     */
    private function synchroniser(Produit $produit, Request $request, JourRetraitRepository $jourRetraitRepository, EntityManagerInterface $em): void
    {
        $jours = $jourRetraitRepository->findAll();

        if (Produit::UNITE_PIECE === $produit->getUnite()) {
            $this->synchroniserStockProduit($produit, $request, $jours, $em);

            return;
        }

        $this->synchroniserVariantes($produit, $request, $jours, $em);
    }

    private function synchroniserStockProduit(Produit $produit, Request $request, array $jours, EntityManagerInterface $em): void
    {
        $stocksSoumis = $request->request->all('stocks');

        foreach ($jours as $jourRetrait) {
            $stockInitial = isset($stocksSoumis[$jourRetrait->getId()]) ? (int) $stocksSoumis[$jourRetrait->getId()] : null;

            if (null === $stockInitial) {
                continue;
            }

            $stock = $produit->getStockPourJour($jourRetrait);

            if (null === $stock) {
                if ($stockInitial <= 0) {
                    continue;
                }
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

    /**
     * Les variantes sont soumises sous la forme :
     *   variantes[<clé>][libelle] = "500g"
     *   variantes[<clé>][stocks][<jourId>] = "10"
     * La clé est soit l'id d'une variante existante, soit "nouveau_0", "nouveau_1"...
     * pour une nouvelle variante à créer. Une variante n'a pas de prix propre :
     * c'est le prix au kg du produit qui s'affiche pour toutes ses variantes.
     */
    private function synchroniserVariantes(Produit $produit, Request $request, array $jours, EntityManagerInterface $em): void
    {
        $variantesSoumises = $request->request->all('variantes');

        $variantesExistantes = [];
        foreach ($produit->getVariantes() as $variante) {
            $variantesExistantes[$variante->getId()] = $variante;
        }

        $idsConserves = [];

        foreach ($variantesSoumises as $cle => $donnees) {
            $libelle = trim((string) ($donnees['libelle'] ?? ''));

            if ('' === $libelle) {
                continue;
            }

            $stocksParJour = $donnees['stocks'] ?? [];

            if (is_numeric($cle) && isset($variantesExistantes[(int) $cle])) {
                $variante = $variantesExistantes[(int) $cle];
                $idsConserves[] = $variante->getId();
            } else {
                $variante = new VarianteProduit();
                $variante->setProduit($produit);
                $em->persist($variante);
            }

            $variante->setLibelle($libelle);

            foreach ($jours as $jourRetrait) {
                $stockInitial = isset($stocksParJour[$jourRetrait->getId()]) ? (int) $stocksParJour[$jourRetrait->getId()] : null;

                if (null === $stockInitial) {
                    continue;
                }

                $stockVariante = $variante->getStockPourJour($jourRetrait);

                if (null === $stockVariante) {
                    if ($stockInitial <= 0) {
                        continue;
                    }
                    $stockVariante = new StockVariante();
                    $stockVariante->setVarianteProduit($variante);
                    $stockVariante->setJourRetrait($jourRetrait);
                    $stockVariante->setStockInitial($stockInitial);
                    $stockVariante->setStockRestant($stockInitial);
                    $em->persist($stockVariante);
                } else {
                    $stockVariante->setStockInitial($stockInitial);
                }
            }
        }

        foreach ($variantesExistantes as $id => $variante) {
            if (!\in_array($id, $idsConserves, true)) {
                $em->remove($variante);
            }
        }
    }
}
