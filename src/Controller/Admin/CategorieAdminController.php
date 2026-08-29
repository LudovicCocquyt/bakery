<?php

namespace App\Controller\Admin;

use App\Entity\Categorie;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/categories')]
#[IsGranted('ROLE_ADMIN')]
class CategorieAdminController extends AbstractController
{
    #[Route('', name: 'admin_categories_index', methods: ['GET'])]
    public function index(CategorieRepository $categorieRepository): Response
    {
        return $this->render('admin/categorie/index.html.twig', [
            'categories' => $categorieRepository->trouverTriees(),
        ]);
    }

    #[Route('/nouvelle', name: 'admin_categories_nouvelle', methods: ['POST'])]
    public function nouvelle(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('ajouter_categorie', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('erreur', 'Formulaire expiré, réessaie.');

            return $this->redirectToRoute('admin_categories_index');
        }

        $nom = trim((string) $request->request->get('nom'));

        if ('' === $nom) {
            $this->addFlash('erreur', 'Le nom de la catégorie est obligatoire.');

            return $this->redirectToRoute('admin_categories_index');
        }

        $categorie = new Categorie();
        $categorie->setNom($nom);
        $categorie->setOrdre((int) $request->request->get('ordre', 0));

        $em->persist($categorie);
        $em->flush();

        $this->addFlash('succes', sprintf('Catégorie "%s" créée.', $nom));

        return $this->redirectToRoute('admin_categories_index');
    }

    #[Route('/{id}/modifier', name: 'admin_categories_modifier', methods: ['POST'])]
    public function modifier(Categorie $categorie, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('modifier_categorie_'.$categorie->getId(), (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('erreur', 'Formulaire expiré, réessaie.');

            return $this->redirectToRoute('admin_categories_index');
        }

        $nom = trim((string) $request->request->get('nom'));

        if ('' === $nom) {
            $this->addFlash('erreur', 'Le nom de la catégorie est obligatoire.');

            return $this->redirectToRoute('admin_categories_index');
        }

        $categorie->setNom($nom);
        $categorie->setOrdre((int) $request->request->get('ordre', 0));
        $em->flush();

        $this->addFlash('succes', 'Catégorie mise à jour.');

        return $this->redirectToRoute('admin_categories_index');
    }

    #[Route('/{id}/supprimer', name: 'admin_categories_supprimer', methods: ['POST'])]
    public function supprimer(Categorie $categorie, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('supprimer_categorie_'.$categorie->getId(), (string) $request->request->get('_csrf_token'))) {
            // Les produits de cette catégorie ne sont pas supprimés, ils
            // repassent simplement "sans catégorie" (categorie_id nullable).
            foreach ($categorie->getProduits() as $produit) {
                $produit->setCategorie(null);
            }

            $em->remove($categorie);
            $em->flush();
            $this->addFlash('succes', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('admin_categories_index');
    }
}
