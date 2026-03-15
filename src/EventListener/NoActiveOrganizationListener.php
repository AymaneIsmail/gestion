<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\NoActiveOrganizationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class NoActiveOrganizationListener
{
    public function __construct(
        private readonly RouterInterface $router,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->getThrowable() instanceof NoActiveOrganizationException) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->router->generate('app_organization_create'),
        ));
    }
}
