<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Defensive response headers (SRS 50). The account-password/session-device
 * screens this file used to test (SRS 50.1, 50.2, 50.4) are gone — fully
 * replaced by the Next.js app (Phase D/F, docs/12-Stack-Migration-
 * Implementation-Plan.md) — and every rule they enforced (current password
 * required, changing it revokes every other token/session but this one, a
 * session/token cannot be revoked for somebody else's account) is proven
 * instead at `tests/Feature/Api/ApiAuthenticationTest.php`.
 */
class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($delta);
    }

    public function test_every_web_response_carries_the_defensive_headers(): void
    {
        // `/login` rather than an `/app/*` screen: `SecurityHeaders` is
        // global middleware (bootstrap/app.php), so any always-reachable
        // web route proves the same thing a decommissioned one used to.
        $response = $this->get('/login')->assertOk();

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('same-origin', $response->headers->get('Referrer-Policy'));
    }

    public function test_the_json_api_gets_them_too(): void
    {
        // A JSON endpoint that can be framed or MIME-sniffed is one that can
        // be read across origins.
        $response = $this->getJson('/api/v1/health')->assertOk();

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }
}
