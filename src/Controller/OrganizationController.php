<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\User;
use App\Form\OrganizationType;
use App\Repository\OrganizationRepository;
use App\Service\OrganizationContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/organisation')]
class OrganizationController extends AbstractController
{
    #[Route('/choisir', name: 'app_organization_select')]
    public function select(
        Request $request,
        OrganizationRepository $organizationRepository,
        OrganizationContext $organizationContext,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $organizations = $organizationRepository->findByUser($user);

        // Auto-select if user has exactly one org
        if (count($organizations) === 1 && !$request->isMethod('POST')) {
            $organizationContext->setActiveOrganization($organizations[0]);

            return $this->redirectToRoute('app_product_index');
        }

        if ($request->isMethod('POST')) {
            $id = $request->request->get('organization_id', '');

            if (!Uuid::isValid($id)) {
                $this->addFlash('error', 'Organisation invalide.');

                return $this->redirectToRoute('app_organization_select');
            }

            $organization = $organizationRepository->find($id);

            if ($organization === null || !$user->isMemberOf($organization)) {
                $this->addFlash('error', 'Vous n\'êtes pas membre de cette organisation.');

                return $this->redirectToRoute('app_organization_select');
            }

            $organizationContext->setActiveOrganization($organization);

            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('organization/select.html.twig', [
            'organizations' => $organizations,
        ]);
    }

    #[Route('/creer', name: 'app_organization_create')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $organization = new Organization();
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($organization);
            $em->persist(new Membership($user, $organization));
            $em->flush();

            $this->addFlash('success', 'Organisation créée avec succès.');

            return $this->redirectToRoute('app_organization_select');
        }

        return $this->render('organization/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/changer', name: 'app_organization_switch')]
    public function switch(OrganizationContext $organizationContext): Response
    {
        $organizationContext->clear();

        return $this->redirectToRoute('app_organization_select');
    }
}
