<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\OrganizationContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class OrganizationExtension extends AbstractExtension
{
    public function __construct(private readonly OrganizationContext $organizationContext) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('active_organization', fn () => $this->organizationContext->getActiveOrganization()),
        ];
    }
}
