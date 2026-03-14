<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\OrganizationContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Entity\User;

/**
 * Enforces that:
 * 1. All routes except /login and /organisation/choisir require an active organization.
 * 2. The active organization in session actually belongs to the authenticated user.
 *
 * This prevents session tampering (e.g. injecting a foreign org ID).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
final class OrganizationAccessListener
{
    private const EXCLUDED_ROUTES = [
        'app_login',
        'app_logout',
        'app_organization_select',
        '_wdt',
        '_profiler',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly OrganizationContext $organizationContext,
        private readonly RouterInterface $router,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route', '');

        // Skip excluded routes and sub-requests
        foreach (self::EXCLUDED_ROUTES as $excluded) {
            if (str_starts_with($route, $excluded)) {
                return;
            }
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Validate that the session org belongs to this user
        if ($this->organizationContext->hasActiveOrganization()) {
            if (!$this->organizationContext->isUserMemberOfActiveOrganization($user)) {
                // Session org does not belong to this user — clear and redirect
                $this->organizationContext->clear();
                $event->setResponse(new RedirectResponse($this->router->generate('app_organization_select')));

                return;
            }
        } else {
            // No org in session — redirect to selection
            $event->setResponse(new RedirectResponse($this->router->generate('app_organization_select')));
        }
    }
}
