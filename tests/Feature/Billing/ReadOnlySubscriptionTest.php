<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * What a read-only subscription actually does (ADR-029, ADR-030, SRS 49.3).
 *
 * The asymmetry is the whole point and the thing most likely to be got wrong:
 * writes are refused, reads and exports never are. A company in arrears can
 * still open every screen, run every report and take their data with them —
 * the data is theirs — they simply cannot add to it until the account is
 * settled.
 *
 * Every case here used to exercise this through the web session and the
 * Blade `/app/*` screens (a redirect-with-flash on refusal, `assertOk` on a
 * page load). Those screens are gone (Phase D/F, docs/12-Stack-Migration-
 * Implementation-Plan.md) — the customer's own surface is the Next.js app
 * now, which only ever talks to the API — so every case is proven here
 * against the API instead, which `EnforceSubscriptionState` already lists
 * its own api/v1 exceptions for (auth/logout, auth/switch-company,
 * subscription-invoices-pay, report-jobs, exports) alongside the web ones,
 * confirming the API path was always meant to carry this rule too, not
 * just the Blade one.
 */
class ReadOnlySubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private User $owner;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->token = app(IssueApiToken::class)
            ->forUser($this->owner, $this->delta->id, 'Test')['plain'];
    }

    private function api(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token);

        return $this;
    }

    private function contract(string $status): SubscriptionContract
    {
        return SubscriptionContract::create([
            'contract_number' => 'SUB-2026-0001',
            'status' => $status,
            'start_date' => '2026-01-01',
            'billing_cycle' => 'MONTHLY',
            'amount' => '25000.0000',
            'currency' => 'BDT',
            'read_only_at' => $status === 'READ_ONLY' ? CarbonImmutable::now() : null,
        ]);
    }

    private function createVendorPayload(): array
    {
        return [
            'name' => 'Juki Bangladesh Ltd',
            'code' => 'JUKI-BD',
            'vendor_type' => 'BOTH',
            'status' => 'ACTIVE',
        ];
    }

    public function test_with_no_contract_nothing_is_restricted(): void
    {
        // A company being onboarded, or a self-hosted deployment with no
        // billing at all, must not be locked out by the absence of a row.
        $this->api()->postJson('/api/v1/vendors', $this->createVendorPayload())->assertCreated();
    }

    public function test_an_active_subscription_allows_writing(): void
    {
        $this->contract('ACTIVE');

        $this->api()->postJson('/api/v1/vendors', $this->createVendorPayload())->assertCreated();
    }

    public function test_a_read_only_subscription_refuses_a_write(): void
    {
        $this->contract('READ_ONLY');

        $this->api()->postJson('/api/v1/vendors', $this->createVendorPayload())
            ->assertStatus(402)
            ->assertJsonPath('code', 'SUBSCRIPTION_READ_ONLY');

        $this->assertSame(0, Vendor::count());
    }

    public function test_a_read_only_subscription_still_serves_every_screen(): void
    {
        $this->contract('READ_ONLY');

        foreach (['/api/v1/assets', '/api/v1/work-orders', '/api/v1/report-jobs', '/api/v1/subscription/invoices'] as $path) {
            // The data belongs to the customer (ADR-030). Being in arrears is
            // not a reason to hide it from them.
            $this->api()->getJson($path)->assertOk();
        }
    }

    public function test_a_read_only_subscription_still_allows_exports(): void
    {
        $this->contract('READ_ONLY');

        // Explicitly: a customer must always be able to retrieve their own
        // data (SRS 49.3), and an export is a POST.
        $this->api()->postJson('/api/v1/exports', [
            'type' => 'assets',
            'format' => 'CSV',
        ])->assertCreated();

        $this->assertSame(1, ExportJob::count());
    }

    public function test_a_read_only_subscription_still_allows_paying_the_bill(): void
    {
        $contract = $this->contract('READ_ONLY');

        $invoice = SubscriptionInvoice::create([
            'subscription_contract_id' => $contract->id,
            'invoice_number' => 'INV-1',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => '25000.0000',
            'total' => '25000.0000',
            'balance_due' => '25000.0000',
            'currency' => 'BDT',
            'status' => 'ISSUED',
        ]);

        // Locking a customer out of the endpoint where they would settle the
        // account would be a remarkable way to not get paid.
        $this->api()->postJson("/api/v1/subscription/invoices/{$invoice->id}/pay", [
            'amount' => '25000',
            'method' => 'BANK_TRANSFER',
            'payment_reference' => 'TRF-1',
        ])->assertCreated();

        $this->assertSame('PAID', $invoice->fresh()->status);
    }

    public function test_a_read_only_subscription_still_allows_signing_out(): void
    {
        $this->contract('READ_ONLY');

        $this->api()->postJson('/api/v1/auth/logout')->assertNoContent();
    }

    public function test_a_past_due_subscription_still_allows_writing(): void
    {
        $this->contract('PAST_DUE');

        // Being late is not the same as being locked out. The grace period
        // exists so a customer has time to pay without work stopping.
        $this->api()->postJson('/api/v1/vendors', $this->createVendorPayload())->assertCreated();
    }

    public function test_another_companys_contract_does_not_restrict_this_one(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        TenantFixture::actingAsTenant($other);

        SubscriptionContract::create([
            'contract_number' => 'SUB-BTL-1',
            'status' => 'READ_ONLY',
            'start_date' => '2026-01-01',
            'amount' => '1000.0000',
            'read_only_at' => CarbonImmutable::now(),
        ]);

        TenantFixture::actingAsTenant($this->delta);

        $this->api()->postJson('/api/v1/vendors', $this->createVendorPayload())->assertCreated();
    }
}
