<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Exception\NoActiveOrganizationException;
use App\Repository\OrganizationRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Manages the active organization stored in the user's session.
 *
 * Acts as the single point of truth for "which organization is currently active".
 * All repository queries scoped to an organization should use this service.
 */
final class OrganizationContext
{
    private const SESSION_KEY = 'active_organization_id';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly OrganizationRepository $organizationRepository,
    ) {
    }

    public function setActiveOrganization(Organization $organization): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $organization->getId()->toRfc4122());
    }

    public function getActiveOrganization(): ?Organization
    {
        $session = $this->requestStack->getSession();
        $id = $session->get(self::SESSION_KEY);

        if ($id === null) {
            return null;
        }

        return $this->organizationRepository->find($id);
    }

    /**
     * Returns the active organization or throws if none is selected.
     * Use this in controllers where an org is required.
     */
    public function requireActiveOrganization(): Organization
    {
        $organization = $this->getActiveOrganization();

        if ($organization === null) {
            throw new NoActiveOrganizationException();
        }

        return $organization;
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    public function hasActiveOrganization(): bool
    {
        return $this->requestStack->getSession()->has(self::SESSION_KEY);
    }

    /**
     * Verifies that the given user is a member of the currently active organization.
     */
    public function isUserMemberOfActiveOrganization(User $user): bool
    {
        $organization = $this->getActiveOrganization();

        if ($organization === null) {
            return false;
        }

        return $user->isMemberOf($organization);
    }
}
