<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tag;
use App\Form\TagType;
use App\Repository\TagRepository;
use App\Service\OrganizationContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tags')]
class TagController extends AbstractController
{
    #[Route('', name: 'app_tag_index', methods: ['GET'])]
    public function index(
        TagRepository $tagRepository,
        OrganizationContext $organizationContext,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();

        return $this->render('tag/index.html.twig', [
            'tags' => $tagRepository->findByOrganization($organization),
        ]);
    }

    #[Route('/nouveau', name: 'app_tag_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        OrganizationContext $organizationContext,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $tag = new Tag();
        $tag->setOrganization($organization);

        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($tag);
            $em->flush();
            $this->addFlash('success', 'Tag créé avec succès.');

            return $this->redirectToRoute('app_tag_index');
        }

        return $this->render('tag/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_tag_edit', methods: ['GET', 'POST'])]
    public function edit(
        string $id,
        Request $request,
        TagRepository $tagRepository,
        EntityManagerInterface $em,
        OrganizationContext $organizationContext,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $tag = $tagRepository->findOneByOrganization($id, $organization);

        if ($tag === null) {
            throw $this->createNotFoundException('Tag introuvable.');
        }

        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Tag modifié avec succès.');

            return $this->redirectToRoute('app_tag_index');
        }

        return $this->render('tag/edit.html.twig', [
            'tag' => $tag,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_tag_delete', methods: ['POST'])]
    public function delete(
        string $id,
        Request $request,
        TagRepository $tagRepository,
        EntityManagerInterface $em,
        OrganizationContext $organizationContext,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $tag = $tagRepository->findOneByOrganization($id, $organization);

        if ($tag === null) {
            throw $this->createNotFoundException('Tag introuvable.');
        }

        if ($this->isCsrfTokenValid('delete-tag-' . $id, $request->request->get('_token'))) {
            $em->remove($tag);
            $em->flush();
            $this->addFlash('success', 'Tag supprimé.');
        }

        return $this->redirectToRoute('app_tag_index');
    }
}
