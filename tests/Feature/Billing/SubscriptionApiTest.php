<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Billing\Models\SubscriptionInvoiceLine;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * What the customer owes, over the API (API 25, SRS 40).
 *
 * The property under test that matters most: a read-only contract still
 * lets the customer pay their bill (ADR-029) — the one write the account
 * lockout must never block.
 */
class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $technician;

    private SubscriptionContract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->contract = SubscriptionContract::create([
            'company_id' => $this->delta->id,
            'contract_number' => 'SUB-0001',
            'status' => 'ACTIVE',
            'start_date' => CarbonImmutable::now()->subMonth(),
            'billing_cycle' => 'MONTHLY',
            'amount' => '50000.0000',
            'currency' => 'BDT',
            'grace_period_days' => 14,
            'auto_renew' => true,
            'overage_policy' => 'WARN_ONLY',
        ]);
    }

    private function invoice(array $overrides = []): SubscriptionInvoice
    {
        return SubscriptionInvoice::create(array_merge([
            'company_id' => $this->delta->id,
            'subscription_contract_id' => $this->contract->id,
            'invoice_number' => 'INV-0001',
            'issue_date' => CarbonImmutable::now()->subDays(10),
            'due_date' => CarbonImmutable::now()->addDays(20),
            'subtotal' => '50000.0000',
            'tax' => '0.0000',
            'total' => '50000.0000',
            'currency' => 'BDT',
            'status' => 'ISSUED',
            'paid_amount' => '0.0000',
            'balance_due' => '50000.0000',
        ], $overrides));
    }

    public function test_the_subscription_summary_reports_the_contract_and_outstanding_balance(): void
    {
        $this->invoice();

        $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.contract.status', 'ACTIVE')
            ->assertJsonPath('data.contract.grace_period_days', 14)
            ->assertJsonPath('data.outstanding', '50000.0000');
    }

    public function test_invoices_can_be_listed_and_read(): void
    {
        $invoice = $this->invoice();

        $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/v1/subscription/invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($this->tokenFor($this->owner))
            ->getJson("/api/v1/subscription/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV-0001');
    }

    public function test_an_invoice_line_reports_its_billing_period_and_a_void_reason_is_visible(): void
    {
        $invoice = $this->invoice(['status' => 'VOID', 'void_reason' => 'Duplicate charge']);

        SubscriptionInvoiceLine::create([
            'company_id' => $this->delta->id,
            'subscription_invoice_id' => $invoice->id,
            'description' => 'Monthly subscription',
            'quantity' => '1.0000',
            'unit_price' => '50000.0000',
            'amount' => '50000.0000',
            'period_start' => CarbonImmutable::now()->subMonth()->startOfMonth(),
            'period_end' => CarbonImmutable::now()->subMonth()->endOfMonth(),
        ]);

        $this->withToken($this->tokenFor($this->owner))
            ->getJson("/api/v1/subscription/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.void_reason', 'Duplicate charge')
            ->assertJsonPath('data.lines.0.period_start', CarbonImmutable::now()->subMonth()->startOfMonth()->toDateString())
            ->assertJsonPath('data.lines.0.period_end', CarbonImmutable::now()->subMonth()->endOfMonth()->toDateString());
    }

    public function test_a_payment_can_be_recorded_and_settles_the_balance(): void
    {
        $invoice = $this->invoice();

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/subscription/invoices/{$invoice->id}/pay", [
                'amount' => 50000,
                'method' => 'BANK_TRANSFER',
                'payment_reference' => 'TXN-001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '50000');

        $this->withToken($this->tokenFor($this->owner))
            ->getJson("/api/v1/subscription/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'PAID')
            ->assertJsonPath('data.balance_due', '0.0000');
    }

    public function test_a_payment_larger_than_the_balance_is_refused(): void
    {
        $invoice = $this->invoice();

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/subscription/invoices/{$invoice->id}/pay", [
                'amount' => 999999,
                'method' => 'BANK_TRANSFER',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_a_read_only_contract_still_accepts_a_payment(): void
    {
        $this->contract->forceFill(['status' => 'READ_ONLY', 'read_only_at' => now()])->save();
        $invoice = $this->invoice();

        // The general write gate (EnforceSubscriptionState) must not catch
        // this endpoint: it is the one write a locked-out account still
        // needs.
        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/subscription/invoices/{$invoice->id}/pay", [
                'amount' => 50000,
                'method' => 'BANK_TRANSFER',
            ])
            ->assertCreated();
    }

    public function test_another_companys_invoice_is_not_reachable(): void
    {
        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::actingAsTenant($omega);

        $theirContract = SubscriptionContract::create([
            'company_id' => $omega->id,
            'contract_number' => 'SUB-OMEGA',
            'status' => 'ACTIVE',
            'start_date' => CarbonImmutable::now(),
            'billing_cycle' => 'MONTHLY',
            'amount' => '10000.0000',
            'currency' => 'BDT',
            'grace_period_days' => 14,
        ]);

        $theirInvoice = SubscriptionInvoice::create([
            'company_id' => $omega->id,
            'subscription_contract_id' => $theirContract->id,
            'invoice_number' => 'INV-OMEGA',
            'issue_date' => CarbonImmutable::now(),
            'due_date' => CarbonImmutable::now()->addDays(30),
            'subtotal' => '10000.0000',
            'tax' => '0.0000',
            'total' => '10000.0000',
            'currency' => 'BDT',
            'status' => 'ISSUED',
            'paid_amount' => '0.0000',
            'balance_due' => '10000.0000',
        ]);

        TenantFixture::actingAsTenant($this->delta);

        $this->withToken($this->tokenFor($this->owner))
            ->getJson("/api/v1/subscription/invoices/{$theirInvoice->id}")
            ->assertNotFound();
    }

    public function test_the_endpoints_are_closed_to_a_role_without_billing_access(): void
    {
        $this->withToken($this->tokenFor($this->technician))
            ->getJson('/api/v1/subscription')
            ->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
