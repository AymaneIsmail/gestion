<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ImportControllerTest extends WebTestCase
{
    // ── GET /import/pdf ─────────────────────────────────────────────

    public function testUploadPageRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/import/pdf');

        $this->assertResponseRedirects('/login');
    }

    // ── POST /import/pdf ────────────────────────────────────────────

    public function testProcessRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('POST', '/import/pdf');

        $this->assertResponseRedirects('/login');
    }

    // ── GET /import/pdf/revue ───────────────────────────────────────

    public function testReviewRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/import/pdf/revue');

        $this->assertResponseRedirects('/login');
    }

    // ── POST /import/pdf/confirmer ──────────────────────────────────

    public function testConfirmRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('POST', '/import/pdf/confirmer');

        $this->assertResponseRedirects('/login');
    }
}
