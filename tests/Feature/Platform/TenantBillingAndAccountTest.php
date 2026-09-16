<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Identity\Actions\AttemptLogin;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\PlatformFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Invoicing a customer, and correcting their sign-ins (SRS 40, 3.1).
 *
 * Both existed only in half. The billing machinery could raise an invoice on a
 * schedule and no person could raise one; the customer's details could be
 * created and never edited, so an owner locked out of a closed email account
 * had no way back into the company they own.
 *
 * The half that needs holding down hardest is the credential reset. It hands
 * somebody outside a company the keys to an account inside it — permanently,
 * unlike a support grant, which expires and announces itself — so it has to be
 * reasoned, audited and told to the customer, or it is a back door with a
 * form in front of it.
 */
class TenantBillingAndAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private string $staffToken;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->staff = PlatformFixture::staff();
        $this->staffToken = PlatformFixture::token($this->staff);
    }

    // -- Invoicing ----------------------------------------------------------

    public function test_an_invoice_is_drafted_before_it_is_issued(): void
    {
        $this->giveContract();

        $this->asStaff()
            ->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/invoices', [
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
            ])
            ->assertCreated();

        $invoice = SubscriptionInvoice::withoutGlobalScope(TenantScope::class)->firstOrFail();

        // Two steps on purpose: a draft can be corrected, an issued invoice is
        // a document somebody has been sent.
        $this->assertSame('DRAFT', $invoice->status);
        $this->assertNotNull($invoice->invoice_number);

        $this->asStaff()
            ->postJson('/api/v1/platform/invoices/'.$invoice->id.'/issue')
            ->assertOk();

        $this->assertNotSame('DRAFT', $invoice->fresh()->status);
    }

    public function test_there_is_nothing_to_invoice_without_a_contract(): void
    {
        $this->asStaff()
            ->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/invoices', [
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('period_start');

        // Inventing terms for one invoice would put a figure on a document
        // with nothing behind it.
        $this->assertSame(0, SubscriptionInvoice::withoutGlobalScope(TenantScope::class)->count());
    }

    public function test_a_payment_can_be_recorded_against_an_issued_invoice(): void
    {
        $invoice = $this->issuedInvoice();

        $this->asStaff()
            ->postJson('/api/v1/platform/invoices/'.$invoice->id.'/payments', [
                'amount' => $invoice->balance_due,
                'method' => 'BANK_TRANSFER',
                'payment_reference' => 'TXN-99120',
            ])
            ->assertCreated();

        // Most payments in this market arrive as a transfer somebody in the
        // office reconciles, not as a card the customer enters.
        $this->assertSame('0.0000', $invoice->fresh()->balance_due);
    }

    public function test_the_invoice_belongs_to_the_customer_not_the_platform(): void
    {
        $invoice = $this->issuedInvoice();

        // Written under the customer's tenant, so their own billing screen can
        // see it. An invoice the customer cannot read is not an invoice.
        $this->assertSame($this->delta->id, $invoice->company_id);
    }

    // -- Sign-ins -----------------------------------------------------------

    public function test_a_password_can_be_issued_when_somebody_is_locked_out(): void
    {
        ['token' => $token] = app(IssueApiToken::class)
            ->forUser($this->owner, $this->delta->id, 'Phone');

        DB::table('sessions')->insert([
            'id' => 'owner-session',
            'user_id' => $this->owner->id,
            'ip_address' => '10.0.0.4',
            'user_agent' => 'Firefox',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $response = $this->asStaff()
            ->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/members/'.$this->owner->id.'/reset-password', [
                'reason' => 'Owner locked out; company email account closed. Ticket 5120.',
            ])
            ->assertOk();

        $password = $response->json('data.password');

        $this->assertIsString($password);

        // It works.
        $this->assertTrue(app(AttemptLogin::class)
            ->verify('owner@delta.test', $password, '127.0.0.1')
            ->is($this->owner));

        // And everything the old one could still reach is gone. A reset that
        // leaves a live session or a valid token behind has reset nothing for
        // whoever was misusing it. `IssueApiToken::forUser()` mints a Sanctum
        // token now, and `revokeAllFor()` deletes those outright rather than
        // soft-revoking them the way the legacy `ApiToken` scheme did — the
        // row itself is gone, not merely flagged.
        $this->assertNull(DB::table('sessions')->where('id', 'owner-session')->value('id'));
        $this->assertNull(PersonalAccessToken::find($token->id));
    }

    public function test_a_reset_is_reasoned_audited_and_announced(): void
    {
        $this->asStaff()
            ->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/members/'.$this->owner->id.'/reset-password', [
                'reason' => 'short',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->asStaff()
            ->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/members/'.$this->owner->id.'/reset-password', [
                'reason' => 'Owner locked out; company email account closed. Ticket 5120.',
            ]);

        $this->assertSame(1, AuditLog::withoutGlobalScope(TenantScope::class)
            ->where('entity_label', 'TENANT_PASSWORD_RESET_BY_PLATFORM')
            ->count());

        // The whole justification for platform staff being able to do this is
        // that the customer finds out. An unannounced credential change is
        // indistinguishable from a compromise.
        $this->assertSame(1, Notification::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $this->owner->id)
            ->where('event_type', 'SUPPORT_ACCESS')
            ->count());
    }

    public function test_a_sign_in_address_can_be_corrected(): void
    {
        $this->asStaff()
            ->patchJson('/api/v1/platform/tenants/'.$this->delta->id.'/members/'.$this->owner->id.'/email', [
                'email' => 'owner@delta-apparels.test',
                'reason' => 'Address mistyped at onboarding. Ticket 5120.',
            ])
            ->assertOk();

        $this->assertSame('owner@delta-apparels.test', $this->owner->fresh()->email);
    }

    public function test_somebody_from_another_company_cannot_be_reached(): void
    {
        $rival = TenantFixture::company('Rival Textiles Ltd', 'RTL');
        TenantFixture::factory($rival, 'Savar Unit', 'SAV');
        TenantFixture::actingAsTenant($rival);
        $theirs = TenantFixture::user($rival, 'COMPANY_OWNER', 'owner@rival.test');
        TenantFixture::actingAsTenant($this->delta);

        // The one place this screen could otherwise reach across the tenancy:
        // a user id from another company put into the URL.
        $this->asStaff()
            ->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/members/'.$theirs->id.'/reset-password', [
                'reason' => 'Trying it on, which is exactly what this refuses.',
            ])
            ->assertNotFound();
    }

    // -- Details ------------------------------------------------------------

    public function test_details_can_be_corrected_but_the_code_cannot(): void
    {
        $this->asStaff()
            ->patchJson('/api/v1/platform/tenants/'.$this->delta->id, [
                'name' => 'Delta Apparels Limited',
                'legal_name' => 'Delta Apparels Ltd.',
                'base_currency' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'default_locale' => 'bn',
                // Offered, and ignored: the code is inside every document
                // number this customer has ever issued.
                'code' => 'CHANGED',
            ])
            ->assertOk();

        $company = $this->delta->fresh();

        $this->assertSame('Delta Apparels Limited', $company->name);
        $this->assertSame('DAL', $company->code);
    }

    private function asStaff(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->staffToken);

        return $this;
    }

    private function giveContract(): void
    {
        $this->asStaff()->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/contracts', [
            'contract_number' => 'SUB-0001',
            'start_date' => '2026-01-01',
            'billing_cycle' => 'MONTHLY',
            'amount' => '25000',
            'currency' => 'BDT',
            'grace_period_days' => 14,
            'overage_policy' => 'WARN_ONLY',
        ]);
    }

    private function issuedInvoice(): SubscriptionInvoice
    {
        $this->giveContract();

        $this->asStaff()->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/invoices', [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ]);

        $invoice = SubscriptionInvoice::withoutGlobalScope(TenantScope::class)->firstOrFail();

        $this->asStaff()->postJson('/api/v1/platform/invoices/'.$invoice->id.'/issue');

        return $invoice->fresh();
    }
}
