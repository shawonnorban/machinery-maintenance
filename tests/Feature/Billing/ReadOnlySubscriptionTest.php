<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Asset\Models\Asset;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Models\ReportJob;
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
 */
class ReadOnlySubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private User $owner;

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

    public function test_with_no_contract_nothing_is_restricted(): void
    {
        // A company being onboarded, or a self-hosted deployment with no
        // billing at all, must not be locked out by the absence of a row.
        $this->actingAs($this->owner)
            ->post('/app/vendors', [
                'name' => 'Juki Bangladesh Ltd',
                'code' => 'JUKI-BD',
                'vendor_type' => 'BOTH',
                'status' => 'ACTIVE',
            ])
            ->assertRedirect();
    }

    public function test_an_active_subscription_allows_writing(): void
    {
        $this->contract('ACTIVE');

        $this->actingAs($this->owner)
            ->post('/app/vendors', [
                'name' => 'Juki Bangladesh Ltd',
                'code' => 'JUKI-BD',
                'vendor_type' => 'BOTH',
                'status' => 'ACTIVE',
            ])
            ->assertRedirect();
    }

    public function test_a_read_only_subscription_refuses_a_write(): void
    {
        $this->contract('READ_ONLY');

        $this->actingAs($this->owner)
            ->from('/app/vendors')
            ->post('/app/vendors', [
                'name' => 'Juki Bangladesh Ltd',
                'code' => 'JUKI-BD',
                'vendor_type' => 'BOTH',
                'status' => 'ACTIVE',
            ])
            ->assertRedirect('/app/vendors')
            ->assertSessionHas('error');

        $this->assertSame(0, Vendor::count());
    }

    public function test_a_read_only_subscription_still_serves_every_screen(): void
    {
        $this->contract('READ_ONLY');

        foreach (['/app/dashboard', '/app/assets', '/app/work-orders', '/app/reports', '/app/billing'] as $path) {
            // The data belongs to the customer (ADR-030). Being in arrears is
            // not a reason to hide it from them.
            $this->actingAs($this->owner)->get($path)->assertOk();
        }
    }

    public function test_a_read_only_subscription_still_allows_exports(): void
    {
        $this->contract('READ_ONLY');

        // Explicitly: a customer must always be able to retrieve their own data
        // (SRS 49.3), and an export is a POST.
        $this->actingAs($this->owner)
            ->post('/app/reports/asset_register/export', ['format' => 'CSV'])
            ->assertRedirect(route('app.reports.jobs'));

        $this->assertSame(1, ReportJob::count());
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

        // Locking a customer out of the page where they would settle the
        // account would be a remarkable way to not get paid.
        $this->actingAs($this->owner)
            ->post(route('app.billing.invoice.pay', $invoice), [
                'amount' => '25000',
                'method' => 'BANK_TRANSFER',
                'payment_reference' => 'TRF-1',
            ])
            ->assertRedirect(route('app.billing.invoice', $invoice));

        $this->assertSame('PAID', $invoice->fresh()->status);
    }

    public function test_a_read_only_subscription_still_allows_signing_out(): void
    {
        $this->contract('READ_ONLY');

        $this->actingAs($this->owner)->post('/logout')->assertRedirect();
    }

    public function test_the_api_is_refused_with_a_named_code_not_a_bare_403(): void
    {
        $this->contract('READ_ONLY');

        $response = $this->actingAs($this->owner)
            ->postJson('/app/vendors', [
                'name' => 'Juki Bangladesh Ltd',
                'code' => 'JUKI-BD',
                'vendor_type' => 'BOTH',
                'status' => 'ACTIVE',
            ]);

        // A billing state and a permission problem are fixed by different
        // people, so the answer says which one this is.
        $response->assertStatus(402)->assertJsonPath('code', 'SUBSCRIPTION_READ_ONLY');
    }

    public function test_a_past_due_subscription_still_allows_writing(): void
    {
        $this->contract('PAST_DUE');

        // Being late is not the same as being locked out. The grace period
        // exists so a customer has time to pay without work stopping.
        $this->actingAs($this->owner)
            ->post('/app/vendors', [
                'name' => 'Juki Bangladesh Ltd',
                'code' => 'JUKI-BD',
                'vendor_type' => 'BOTH',
                'status' => 'ACTIVE',
            ])
            ->assertRedirect();
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

        $this->actingAs($this->owner)
            ->post('/app/vendors', [
                'name' => 'Juki Bangladesh Ltd',
                'code' => 'JUKI-BD',
                'vendor_type' => 'BOTH',
                'status' => 'ACTIVE',
            ])
            ->assertRedirect();
    }
}
