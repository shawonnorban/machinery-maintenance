<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Billing\Services\InvoiceBuilder;
use App\Modules\Billing\Services\PaymentRecorder;
use App\Modules\Billing\Services\UsageMeter;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Invoices, payments and corrections (SRS 40, ERD Section 19).
 *
 * Money is the part of this system a customer checks line by line. Two rules
 * carry most of the weight: an issued invoice never changes, and what is owed
 * is derived from the receipts attached rather than kept as a running total
 * that can drift away from them.
 */
class InvoicingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private SubscriptionContract $contract;

    private string $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        CarbonImmutable::setTestNow('2026-06-15 09:00:00');

        // A real account: recorded_by is a foreign key, because a receipt that
        // names nobody is a receipt nobody can be asked about.
        $this->actor = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test')->id;
        TenantFixture::actingAsTenant($this->delta);

        $this->contract = SubscriptionContract::create([
            'contract_number' => 'SUB-2026-0001',
            'status' => 'ACTIVE',
            'start_date' => '2026-01-01',
            'billing_cycle' => 'MONTHLY',
            'amount' => '25000.0000',
            'currency' => 'BDT',
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function issuedInvoice(string $taxRate = '0'): SubscriptionInvoice
    {
        $builder = app(InvoiceBuilder::class);

        $invoice = $builder->draft(
            $this->contract,
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
            $taxRate,
        );

        return $builder->issue($invoice);
    }

    public function test_a_flat_contract_produces_one_priced_line(): void
    {
        $invoice = $this->issuedInvoice();

        $this->assertCount(1, $invoice->lines);
        $this->assertSame('FLAT', $invoice->lines->first()->metric);
        $this->assertSame(25000.0, (float) $invoice->total);
        $this->assertSame(25000.0, (float) $invoice->balance_due);
    }

    public function test_tax_is_computed_on_the_lines_not_typed_in(): void
    {
        $invoice = $this->issuedInvoice('15');

        // 15% of 25,000, to the cent. Floating point arithmetic here produces
        // totals that are out by a hundredth, and an invoice out by a hundredth
        // is an invoice somebody disputes (ADR-063).
        $this->assertSame(3750.0, (float) $invoice->tax);
        $this->assertSame(28750.0, (float) $invoice->total);
    }

    public function test_a_metered_contract_is_priced_from_measured_usage(): void
    {
        WorkOrderFixture::runningAsset($this->delta, $this->dhaka, 'SEW-DHK-00412');
        WorkOrderFixture::runningAsset($this->delta, $this->dhaka, 'SEW-DHK-00413');

        app(UsageMeter::class)->measure($this->delta->id);

        $this->contract->forceFill([
            'pricing_model_json' => ['type' => 'METERED', 'rates' => ['ASSETS' => '300']],
        ])->save();

        $invoice = $this->issuedInvoice();

        $line = $invoice->lines->firstWhere('metric', 'ASSETS');

        // Priced from what was measured, not from what the salesperson assumed
        // (ADR-028).
        $this->assertNotNull($line);
        $this->assertSame(2.0, (float) $line->quantity);
        $this->assertSame(600.0, (float) $invoice->total);
    }

    public function test_an_issued_invoice_cannot_be_recalculated(): void
    {
        $invoice = $this->issuedInvoice();

        // The totals on a document the customer has already seen do not move.
        $this->expectException(ValidationException::class);

        app(InvoiceBuilder::class)->recalculate($invoice);
    }

    public function test_an_invoice_for_nothing_is_refused(): void
    {
        $this->contract->forceFill(['amount' => '0'])->save();

        $invoice = app(InvoiceBuilder::class)->draft(
            $this->contract->fresh(),
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
        );

        // A zero invoice is a document somebody has to explain.
        $this->expectException(ValidationException::class);

        app(InvoiceBuilder::class)->issue($invoice);
    }

    public function test_a_partial_payment_leaves_the_balance_visible(): void
    {
        $invoice = $this->issuedInvoice();

        app(PaymentRecorder::class)->record($invoice, [
            'amount' => '10000',
            'method' => 'BANK_TRANSFER',
            'payment_reference' => 'TRF-001',
        ], $this->actor);

        $invoice = $invoice->fresh();

        // These customers pay across two or three transfers. An invoice that
        // only knows PAID and UNPAID cannot say what is still outstanding.
        $this->assertSame('PARTIALLY_PAID', $invoice->status);
        $this->assertSame(10000.0, (float) $invoice->paid_amount);
        $this->assertSame(15000.0, (float) $invoice->balance_due);
    }

    public function test_settling_the_balance_marks_the_invoice_paid(): void
    {
        $invoice = $this->issuedInvoice();
        $recorder = app(PaymentRecorder::class);

        $recorder->record($invoice, ['amount' => '10000', 'method' => 'BANK_TRANSFER', 'payment_reference' => 'T1'], $this->actor);
        $recorder->record($invoice->fresh(), ['amount' => '15000', 'method' => 'CASH', 'payment_reference' => 'T2'], $this->actor);

        $invoice = $invoice->fresh();

        $this->assertSame('PAID', $invoice->status);
        $this->assertSame(0.0, (float) $invoice->balance_due);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_overpayment_is_refused_rather_than_absorbed(): void
    {
        $invoice = $this->issuedInvoice();

        // A real thing, but a decision somebody has to make deliberately — a
        // credit or a refund — not something a payment form swallows.
        $this->expectException(ValidationException::class);

        app(PaymentRecorder::class)->record($invoice, [
            'amount' => '30000',
            'method' => 'BANK_TRANSFER',
            'payment_reference' => 'TOO-MUCH',
        ], $this->actor);
    }

    public function test_reversing_a_payment_restores_the_balance_and_leaves_both_rows(): void
    {
        $invoice = $this->issuedInvoice();
        $recorder = app(PaymentRecorder::class);

        $payment = $recorder->record($invoice, [
            'amount' => '25000', 'method' => 'CHEQUE', 'payment_reference' => 'CHQ-9',
        ], $this->actor);

        $this->assertSame('PAID', $invoice->fresh()->status);

        $recorder->reverse($payment, 'Cheque bounced', $this->actor);

        $invoice = $invoice->fresh();

        // The receipt that was recorded and the fact that it came back are both
        // visible; neither is edited away.
        $this->assertSame(25000.0, (float) $invoice->balance_due);
        $this->assertSame('REVERSED', $payment->fresh()->status);
        $this->assertSame(1, $payment->refunds()->count());
    }

    public function test_a_credit_note_reduces_what_is_owed_without_touching_the_invoice(): void
    {
        $invoice = $this->issuedInvoice();

        app(PaymentRecorder::class)->creditNote($invoice, '5000', 'Agreed discount for the outage', $this->actor);

        $invoice = $invoice->fresh();

        // The invoice total is untouched — the document the customer holds is
        // still the document we hold — while the balance reflects the credit.
        $this->assertSame(25000.0, (float) $invoice->total);
        $this->assertSame(20000.0, (float) $invoice->balance_due);
        $this->assertSame('PARTIALLY_PAID', $invoice->status);
    }

    public function test_a_credit_note_against_a_draft_is_refused(): void
    {
        $invoice = app(InvoiceBuilder::class)->draft(
            $this->contract,
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
        );

        // A draft is corrected by editing it. A credit note against one would
        // explain a change nobody ever saw.
        $this->expectException(ValidationException::class);

        app(PaymentRecorder::class)->creditNote($invoice, '100', 'Mistake', $this->actor);
    }

    public function test_a_paid_invoice_cannot_be_voided(): void
    {
        $invoice = $this->issuedInvoice();

        app(PaymentRecorder::class)->record($invoice, [
            'amount' => '25000', 'method' => 'CASH', 'payment_reference' => 'C-1',
        ], $this->actor);

        // Money has changed hands. Voiding would leave a payment attached to a
        // document saying it was never owed.
        $this->expectException(ValidationException::class);

        app(PaymentRecorder::class)->void($invoice->fresh(), 'Raised in error');
    }

    public function test_voiding_an_unpaid_invoice_clears_what_is_owed(): void
    {
        $invoice = $this->issuedInvoice();

        $voided = app(PaymentRecorder::class)->void($invoice, 'Raised against the wrong contract');

        $this->assertSame('VOID', $voided->status);
        $this->assertSame(0.0, (float) $voided->balance_due);
        $this->assertSame('Raised against the wrong contract', $voided->void_reason);
    }
}
