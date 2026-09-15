<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Billing\Models\PlatformExpense;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Billing\Models\SubscriptionPayment;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Platform\Actions\ManageSupportTicket;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\PlatformFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The Platform API (docs/03-Platform-API-Specification.md).
 *
 * Two properties matter more than any one endpoint: a valid tenant token
 * must never reach `/platform/*` (404, not 403 — existence itself is not
 * information to hand out), and support access must always be time-boxed,
 * reasoned and audited at every step.
 */
class PlatformApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $staff;

    private string $staffToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        $this->staff = PlatformFixture::staff();
        $this->staffToken = PlatformFixture::token($this->staff);
    }

    private function asStaff(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->staffToken);

        return $this;
    }

    // -- The gate -------------------------------------------------------

    public function test_a_tenant_token_cannot_reach_the_platform_area(): void
    {
        $token = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Owner device')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/platform/tenants')
            ->assertNotFound();
    }

    public function test_a_non_admin_cannot_log_in_to_the_platform(): void
    {
        $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'owner@delta.test',
            'password' => 'correct-horse-battery',
            'device_name' => 'x',
        ])->assertStatus(401);
    }

    public function test_a_platform_admin_logs_in_and_reads_their_own_identity(): void
    {
        $response = $this->postJson('/api/v1/platform/auth/login', [
            'email' => $this->staff->email,
            'password' => 'correct-horse-battery',
            'device_name' => 'Ops laptop',
        ])->assertCreated();

        $token = $response->json('data.access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/platform/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $this->staff->email);
    }

    public function test_no_token_is_refused(): void
    {
        $this->getJson('/api/v1/platform/tenants')->assertStatus(401);
    }

    // -- Tenants ----------------------------------------------------------

    public function test_tenants_are_listed_with_counts(): void
    {
        $this->asStaff()->getJson('/api/v1/platform/tenants')
            ->assertOk()
            ->assertJsonFragment(['code' => 'DAL']);
    }

    public function test_a_new_tenant_is_onboarded(): void
    {
        $response = $this->asStaff()->postJson('/api/v1/platform/tenants', [
            'name' => 'Rival Textiles Ltd',
            'code' => 'RTL',
            'base_currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'default_locale' => 'en',
            'factory_name' => 'Savar Unit',
            'factory_code' => 'SAV',
            'owner_name' => 'Rival Owner',
            'owner_email' => 'owner@rival.test',
        ])->assertCreated();

        $this->assertNotEmpty($response->json('data.password'));
        $this->assertDatabaseHas('companies', ['code' => 'RTL']);
    }

    public function test_tenant_detail_and_update(): void
    {
        $this->asStaff()->getJson('/api/v1/platform/tenants/'.$this->delta->id)
            ->assertOk()
            ->assertJsonPath('data.code', 'DAL');

        $this->asStaff()->patchJson('/api/v1/platform/tenants/'.$this->delta->id, [
            'name' => 'Delta Apparels (Renamed)',
            'base_currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'default_locale' => 'en',
        ])->assertOk()->assertJsonPath('data.name', 'Delta Apparels (Renamed)');
    }

    public function test_suspending_a_tenant_revokes_its_tokens_immediately(): void
    {
        $token = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Owner device')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->asStaff()->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/suspend', [
            'reason' => 'Non-payment for two billing cycles',
        ])->assertOk()->assertJsonPath('data.status', 'SUSPENDED');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        $this->asStaff()->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/reactivate')
            ->assertOk()->assertJsonPath('data.status', 'ACTIVE');
    }

    /**
     * `assertCompanyUsable()` is defence in depth for a token that survives
     * its company's suspension by some path other than this endpoint's own
     * revocation (a legacy `ApiToken`, or a future suspension route that
     * forgets to call it) — simulated here by suspending the company
     * directly, without going through the revoking endpoint.
     */
    public function test_a_token_surviving_a_suspension_is_still_refused(): void
    {
        $token = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Owner device')['plain'];

        $this->delta->forceFill(['status' => 'SUSPENDED', 'suspension_reason' => 'Simulated external suspension'])->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('code', 'TENANT_SUSPENDED');
    }

    public function test_closing_and_restoring_a_tenant(): void
    {
        $this->asStaff()->deleteJson('/api/v1/platform/tenants/'.$this->delta->id, [
            'confirm_code' => $this->delta->code,
            'reason' => 'Customer requested account closure',
        ])->assertNoContent();

        $this->assertSoftDeleted('companies', ['id' => $this->delta->id]);

        $this->asStaff()->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/restore')
            ->assertOk();

        $this->assertDatabaseHas('companies', ['id' => $this->delta->id, 'deleted_at' => null]);
    }

    public function test_purge_requires_the_tenant_to_already_be_closed(): void
    {
        $this->asStaff()->deleteJson('/api/v1/platform/tenants/'.$this->delta->id.'/purge', [
            'confirm_code' => $this->delta->code,
            'reason' => 'Not closed yet',
        ])->assertNotFound();

        $this->asStaff()->deleteJson('/api/v1/platform/tenants/'.$this->delta->id, [
            'confirm_code' => $this->delta->code,
            'reason' => 'Customer requested account closure',
        ])->assertNoContent();

        $this->asStaff()->deleteJson('/api/v1/platform/tenants/'.$this->delta->id.'/purge', [
            'confirm_code' => $this->delta->code,
            'reason' => 'Retention period elapsed',
        ])->assertNoContent();

        $this->assertDatabaseMissing('companies', ['id' => $this->delta->id]);
    }

    // -- Contracts and entitlements ---------------------------------------

    public function test_a_contract_is_created_and_entitlements_adjusted(): void
    {
        $this->asStaff()->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/contracts', [
            'contract_number' => 'CN-0001',
            'start_date' => now()->toDateString(),
            'billing_cycle' => 'MONTHLY',
            'amount' => 5000,
            'currency' => 'BDT',
            'grace_period_days' => 7,
            'overage_policy' => 'BLOCK',
            'included_factories' => 2,
            'included_assets' => 100,
            'included_users' => 10,
        ])->assertCreated()->assertJsonPath('data.contract_number', 'CN-0001');

        $this->asStaff()->patchJson('/api/v1/platform/tenants/'.$this->delta->id.'/entitlements', [
            'included_factories' => 5,
            'included_assets' => 500,
            'included_users' => 50,
            'overage_policy' => 'ALLOW_AND_BILL',
        ])->assertOk()->assertJsonPath('data.included_factories', 5);
    }

    // -- Domains ------------------------------------------------------------

    public function test_a_domain_is_added_verified_and_made_primary(): void
    {
        $store = $this->asStaff()->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/domains', [
            'kind' => 'SUBDOMAIN',
            'host' => 'delta',
        ])->assertCreated();

        $domainId = $store->json('data.id');
        $this->assertTrue($store->json('data.is_verified'));

        $this->asStaff()->postJson('/api/v1/platform/domains/'.$domainId.'/primary')
            ->assertOk()->assertJsonPath('data.is_primary', true);

        $this->asStaff()->deleteJson('/api/v1/platform/domains/'.$domainId)->assertNoContent();
    }

    public function test_a_custom_domain_starts_unverified_and_refuses_primary(): void
    {
        $store = $this->asStaff()->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/domains', [
            'kind' => 'CUSTOM',
            'host' => 'erp.deltaapparels.test',
        ])->assertCreated();

        $this->assertFalse($store->json('data.is_verified'));

        $domainId = $store->json('data.id');

        $this->asStaff()->postJson('/api/v1/platform/domains/'.$domainId.'/verify')
            ->assertStatus(409);

        $this->asStaff()->postJson('/api/v1/platform/domains/'.$domainId.'/primary')
            ->assertStatus(409);
    }

    // -- Tenant account recovery --------------------------------------------

    public function test_a_members_email_is_changed_and_notified(): void
    {
        $this->asStaff()->patchJson(
            "/api/v1/platform/tenants/{$this->delta->id}/members/{$this->owner->id}/email",
            ['email' => 'new-owner-address@delta.test', 'reason' => 'Owner lost access to old inbox'],
        )->assertOk()->assertJsonPath('data.email', 'new-owner-address@delta.test');

        $this->assertDatabaseHas('notifications', [
            'company_id' => $this->delta->id,
            'event_type' => 'SUPPORT_ACCESS',
        ]);
    }

    public function test_a_password_reset_revokes_existing_tokens(): void
    {
        $oldToken = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Old device')['plain'];

        $response = $this->asStaff()->postJson(
            "/api/v1/platform/tenants/{$this->delta->id}/members/{$this->owner->id}/reset-password",
            ['reason' => 'Owner forgot password and has no recovery email'],
        )->assertOk();

        $this->assertNotEmpty($response->json('data.password'));

        $this->withHeader('Authorization', 'Bearer '.$oldToken)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_account_recovery_404s_for_a_non_member(): void
    {
        $rival = TenantFixture::company('Rival Textiles Ltd', 'RTL');
        TenantFixture::actingAsTenant($rival);
        $outsider = TenantFixture::user($rival, 'COMPANY_OWNER', 'owner@rival.test');

        $this->asStaff()->patchJson(
            "/api/v1/platform/tenants/{$this->delta->id}/members/{$outsider->id}/email",
            ['email' => 'x@y.test', 'reason' => 'Attempting cross-tenant edit'],
        )->assertNotFound();
    }

    // -- Support access (impersonation) --------------------------------------

    public function test_a_support_grant_is_opened_entered_and_left(): void
    {
        $store = $this->asStaff()->postJson("/api/v1/platform/tenants/{$this->delta->id}/support-grants", [
            'reason' => 'Investigating a reported breakdown export bug',
            'hours' => 2,
        ])->assertCreated();

        $grantId = $store->json('data.id');

        $enter = $this->asStaff()->postJson("/api/v1/platform/support-grants/{$grantId}/enter", [
            'user_id' => $this->owner->id,
        ])->assertCreated();

        $impersonationToken = $enter->json('data.access_token');
        $this->assertSame($this->owner->id, $enter->json('data.acting_as.id'));

        $this->withHeader('Authorization', 'Bearer '.$impersonationToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $this->owner->id);

        $this->asStaff()->postJson("/api/v1/platform/support-grants/{$grantId}/leave")->assertNoContent();

        $this->withHeader('Authorization', 'Bearer '.$impersonationToken)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_entering_a_grant_stamps_impersonated_by_on_the_minted_token(): void
    {
        $store = $this->asStaff()->postJson("/api/v1/platform/tenants/{$this->delta->id}/support-grants", [
            'reason' => 'Correcting a factory record at the customer\'s request',
            'hours' => 1,
        ])->assertCreated();

        $enter = $this->asStaff()->postJson('/api/v1/platform/support-grants/'.$store->json('data.id').'/enter', [
            'user_id' => $this->owner->id,
        ])->assertCreated();

        $record = PersonalAccessToken::findToken($enter->json('data.access_token'));
        $this->assertSame($this->staff->id, $record?->impersonated_by);
    }

    public function test_closing_a_grant_ends_it_and_revokes_any_active_session(): void
    {
        $store = $this->asStaff()->postJson("/api/v1/platform/tenants/{$this->delta->id}/support-grants", [
            'reason' => 'One-off data correction requested by the customer',
            'hours' => 1,
        ])->assertCreated();

        $grantId = $store->json('data.id');

        $enter = $this->asStaff()->postJson("/api/v1/platform/support-grants/{$grantId}/enter", [
            'user_id' => $this->owner->id,
        ])->assertCreated();

        $this->asStaff()->postJson("/api/v1/platform/support-grants/{$grantId}/close")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->withHeader('Authorization', 'Bearer '.$enter->json('data.access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_support_grants_are_listed(): void
    {
        $this->asStaff()->postJson("/api/v1/platform/tenants/{$this->delta->id}/support-grants", [
            'reason' => 'Routine account check requested by the customer',
            'hours' => 1,
        ])->assertCreated();

        $this->asStaff()->getJson('/api/v1/platform/support-grants?active=true')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // -- Support tickets ------------------------------------------------------

    public function test_a_tenant_opens_a_ticket_and_the_platform_replies(): void
    {
        $tenantToken = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Owner device')['plain'];

        $open = $this->withHeader('Authorization', 'Bearer '.$tenantToken)
            ->postJson('/api/v1/support/tickets', [
                'subject' => 'Export button does nothing',
                'body' => 'Clicking export on the breakdown report does not download anything.',
            ])->assertCreated();

        $ticketId = $open->json('data.id');

        $this->asStaff()->getJson('/api/v1/platform/tickets')
            ->assertOk()
            ->assertJsonFragment(['subject' => 'Export button does nothing']);

        $this->asStaff()->postJson("/api/v1/platform/tickets/{$ticketId}/reply", [
            'body' => 'Thanks for the report — please try again, we shipped a fix.',
        ])->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');

        $this->asStaff()->patchJson("/api/v1/platform/tickets/{$ticketId}/status", ['status' => 'RESOLVED'])
            ->assertOk()->assertJsonPath('data.status', 'RESOLVED');

        $this->asStaff()->patchJson("/api/v1/platform/tickets/{$ticketId}/assign", ['assigned_to' => $this->staff->id])
            ->assertOk()->assertJsonPath('data.assignee.id', $this->staff->id);

        $this->withHeader('Authorization', 'Bearer '.$tenantToken)
            ->getJson("/api/v1/support/tickets/{$ticketId}")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages');
    }

    public function test_a_customer_reply_reopens_a_resolved_ticket(): void
    {
        $tenantToken = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Owner device')['plain'];

        $company = Company::withoutGlobalScope(TenantScope::class)->find($this->delta->id);
        $ticket = app(ManageSupportTicket::class)->open($company, $this->owner, 'Question', 'Body text here.');
        app(ManageSupportTicket::class)->setStatus($ticket, $this->staff, 'RESOLVED');

        $this->withHeader('Authorization', 'Bearer '.$tenantToken)
            ->postJson("/api/v1/support/tickets/{$ticket->id}/reply", ['body' => 'Still broken, please reopen.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'OPEN');
    }

    public function test_a_tenant_cannot_reach_another_companys_ticket(): void
    {
        $rival = TenantFixture::company('Rival Textiles Ltd', 'RTL');
        TenantFixture::actingAsTenant($rival);
        $rivalOwner = TenantFixture::user($rival, 'COMPANY_OWNER', 'owner@rival.test');
        TenantFixture::actingAsTenant($this->delta);

        $company = Company::withoutGlobalScope(TenantScope::class)->find($this->delta->id);
        $ticket = app(ManageSupportTicket::class)->open($company, $this->owner, 'Private', 'Body text here.');

        $rivalToken = app(IssueApiToken::class)->forUser($rivalOwner, $rival->id, 'Rival device')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$rivalToken)
            ->getJson("/api/v1/support/tickets/{$ticket->id}")
            ->assertNotFound();
    }

    // -- Finance --------------------------------------------------------------

    public function test_finance_summary_groups_figures_by_currency(): void
    {
        $contract = SubscriptionContract::withoutGlobalScope(TenantScope::class)->create([
            'company_id' => $this->delta->id,
            'contract_number' => 'CN-FIN',
            'status' => 'ACTIVE',
            'start_date' => now()->subMonth(),
            'billing_cycle' => 'MONTHLY',
            'amount' => 1000,
            'currency' => 'BDT',
            'grace_period_days' => 7,
            'overage_policy' => 'BLOCK',
        ]);

        $invoice = SubscriptionInvoice::withoutGlobalScope(TenantScope::class)->create([
            'company_id' => $this->delta->id,
            'subscription_contract_id' => $contract->id,
            'invoice_number' => 'INV-0001',
            'issue_date' => now()->subDays(2),
            'status' => 'ISSUED',
            'currency' => 'BDT',
            'total' => 1000,
            'balance_due' => 1000,
            'due_date' => now()->subDay(),
        ]);

        SubscriptionPayment::withoutGlobalScope(TenantScope::class)->create([
            'company_id' => $this->delta->id,
            'invoice_id' => $invoice->id,
            'payment_reference' => 'PMT-0001',
            'method' => 'BANK_TRANSFER',
            'status' => 'RECEIVED',
            'currency' => 'BDT',
            'amount' => 400,
            'paid_at' => now(),
        ]);

        $this->asStaff()->getJson('/api/v1/platform/finance/summary')
            ->assertOk()
            ->assertJsonPath('data.totals.BDT.invoiced', '1000.0000')
            ->assertJsonPath('data.totals.BDT.received', '400.0000');

        $this->asStaff()->getJson('/api/v1/platform/finance/invoices/overdue')
            ->assertOk()
            ->assertJsonFragment(['invoice_number' => 'INV-0001']);

        $this->asStaff()->getJson('/api/v1/platform/finance/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_platform_expenses_are_recorded_and_removed(): void
    {
        $store = $this->asStaff()->postJson('/api/v1/platform/finance/expenses', [
            'spent_on' => now()->toDateString(),
            'category' => 'HOSTING',
            'description' => 'Monthly server bill',
            'amount' => 150,
            'currency' => 'usd',
            'vendor' => 'CloudCo',
        ])->assertCreated();

        $this->assertSame('USD', $store->json('data.currency'));

        $this->asStaff()->getJson('/api/v1/platform/finance/expenses')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->asStaff()->deleteJson('/api/v1/platform/finance/expenses/'.$store->json('data.id'))
            ->assertNoContent();

        $this->assertDatabaseCount('platform_expenses', 0);
    }

    // -- Platform notifications -------------------------------------------------

    public function test_a_platform_admins_own_notifications_are_listed_and_marked_read(): void
    {
        app(TenantContext::class)->runWithoutTenant(function (): void {
            Notification::withoutGlobalScope(TenantScope::class)->create([
                'user_id' => $this->staff->id,
                'event_type' => 'PLATFORM_TICKET_OPENED',
                'title' => 'New ticket',
                'severity' => 'INFO',
                'locale' => 'en',
            ]);
        });

        $this->asStaff()->getJson('/api/v1/platform/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->asStaff()->postJson('/api/v1/platform/notifications/read-all')->assertNoContent();

        $this->asStaff()->getJson('/api/v1/platform/notifications?filter=UNREAD')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_platform_notifications_never_include_a_tenants_own(): void
    {
        TenantFixture::actingAsTenant($this->delta);

        Notification::create([
            'company_id' => $this->delta->id,
            'user_id' => $this->staff->id,
            'event_type' => 'PLATFORM_TICKET_OPENED',
            'title' => 'Should not appear',
            'severity' => 'INFO',
        ]);

        $this->asStaff()->getJson('/api/v1/platform/notifications?filter=ALL')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
