<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\OrganizationContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/categories')]
class CategoryController extends AbstractController
{
    #[Route('', name: 'app_category_index', methods: ['GET'])]
    public function index(
        CategoryRepository $categoryRepository,
        OrganizationContext $organizationContext,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();

        return $this->render('category/index.html.twig', [
            'categories' => $categoryRepository->findByOrganization($organization),
        ]);
    }

    #[Route('/nouveau', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        OrganizationContext $organizationContext,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $category = new Category();
        $category->setOrganization($organization);

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($category);
            $em->flush();

            $this->addFlash('success', 'Catégorie créée avec succès.');

            return $this->redirectToRoute('app_category_index');
        }

        return $this->render('category/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_category_edit', methods: ['GET', 'POST'])]
    public function edit(
        string $id,
        Request $request,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $em,
        OrganizationContext $organizationContext,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $category = $categoryRepository->findOneByOrganization($id, $organization);

        if ($category === null) {
            throw $this->createNotFoundException('Catégorie introuvable.');
        }

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Catégorie modifiée avec succès.');

            return $this->redirectToRoute('app_category_index');
        }

        return $this->render('category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_category_delete', methods: ['POST'])]
    public function delete(
        string $id,
        Request $request,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $em,
        OrganizationContext $organizationContext,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $category = $categoryRepository->findOneByOrganization($id, $organization);

        if ($category === null) {
            throw $this->createNotFoundException('Catégorie introuvable.');
        }

        if ($this->isCsrfTokenValid('delete-category-' . $id, $request->request->get('_token'))) {
            $em->remove($category);
            $em->flush();
            $this->addFlash('success', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('app_category_index');
    }
}
