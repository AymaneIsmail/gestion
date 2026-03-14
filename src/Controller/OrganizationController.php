<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\OrganizationRepository;
use App\Service\OrganizationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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

    #[Route('/changer', name: 'app_organization_switch')]
    public function switch(OrganizationContext $organizationContext): Response
    {
        $organizationContext->clear();

        return $this->redirectToRoute('app_organization_select');
    }
}
