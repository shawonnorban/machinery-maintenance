<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Billing\Models\PlatformExpense;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Billing\Models\SubscriptionPayment;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * What the business took, is owed, and spent.
 *
 * Every figure is read back from invoices, payments and refunds rather than
 * kept as a running total, so what these tests pin down is the arithmetic: a
 * voided invoice is not money owed, a refund comes off what was received, and
 * nothing is ever summed across two currencies.
 *
 * That last one is the property most worth a test. A single figure adding BDT
 * to USD is not merely imprecise — it is a number that means nothing, and it
 * looks exactly like a number that means something.
 */
class PlatformFinanceTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private string $staffToken;

    private Company $delta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->staff = PlatformFixture::staff();
        $this->staffToken = PlatformFixture::token($this->staff);
    }

    public function test_the_page_renders_with_nothing_recorded(): void
    {
        $this->asStaff()
            ->getJson('/api/v1/platform/finance/summary')
            ->assertOk()
            ->assertJsonPath('data.totals', []);
    }

    public function test_it_adds_up_what_was_invoiced_received_and_still_owed(): void
    {
        $this->invoice('INV-1', '10000.0000', paid: '10000.0000');
        $this->invoice('INV-2', '5000.0000', paid: '1500.0000');

        $this->asStaff()->getJson('/api/v1/platform/finance/summary')
            ->assertOk()
            ->assertJsonPath('data.totals.BDT.invoiced', '15000.0000')
            ->assertJsonPath('data.totals.BDT.received', '11500.0000')
            ->assertJsonPath('data.totals.BDT.due', '3500.0000');
    }

    public function test_a_voided_invoice_is_not_money_owed(): void
    {
        $this->invoice('INV-1', '10000.0000', paid: '0');
        $this->invoice('INV-2', '9999.0000', paid: '0', status: 'VOID');

        // A voided invoice is a document that was withdrawn. Counting it would
        // make the business look owed money nobody owes.
        $this->asStaff()->getJson('/api/v1/platform/finance/summary')
            ->assertOk()
            ->assertJsonPath('data.totals.BDT.invoiced', '10000.0000')
            ->assertJsonPath('data.totals.BDT.due', '10000.0000');
    }

    public function test_a_draft_invoice_is_not_counted_either(): void
    {
        $this->invoice('INV-1', '7000.0000', paid: '0', status: 'DRAFT');

        // A draft has not been sent to anybody, so it is not yet a claim on
        // anyone's money.
        $this->asStaff()->getJson('/api/v1/platform/finance/summary')
            ->assertOk()
            ->assertJsonPath('data.totals', []);
    }

    public function test_spending_comes_off_the_net(): void
    {
        $this->invoice('INV-1', '10000.0000', paid: '10000.0000');

        $this->asStaff()
            ->postJson('/api/v1/platform/finance/expenses', [
                'spent_on' => now()->toDateString(),
                'category' => 'HOSTING',
                'description' => 'Server, August',
                'amount' => '2500',
                'currency' => 'bdt',
            ])
            ->assertCreated();

        // Lowercased on the way in, or "bdt" and "BDT" would be two currencies
        // that never add up to each other.
        $this->asStaff()->getJson('/api/v1/platform/finance/summary')
            ->assertOk()
            ->assertJsonPath('data.totals.BDT.spent', '2500.0000')
            ->assertJsonPath('data.totals.BDT.net', '7500.0000');
    }

    public function test_two_currencies_are_never_added_together(): void
    {
        $this->invoice('INV-1', '10000.0000', paid: '10000.0000');

        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::actingAsTenant($omega);
        $this->invoice('INV-2', '500.0000', paid: '500.0000', company: $omega, currency: 'USD');

        $totals = $this->asStaff()->getJson('/api/v1/platform/finance/summary')
            ->assertOk()
            ->json('data.totals');

        // Two sets of figures, not one meaningless sum.
        $this->assertSame(['BDT', 'USD'], array_keys($totals));
        $this->assertSame('10000.0000', $totals['BDT']['received']);
        $this->assertSame('500.0000', $totals['USD']['received']);
    }

    public function test_customers_are_listed_worst_debt_first(): void
    {
        $this->invoice('INV-1', '1000.0000', paid: '1000.0000');

        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::actingAsTenant($omega);
        $this->invoice('INV-2', '8000.0000', paid: '0', company: $omega);

        // Whoever owes the most, first — that is the reason to open the table.
        $this->asStaff()->getJson('/api/v1/platform/finance/summary')
            ->assertOk()
            ->assertJsonPath('data.customers.0.company.name', 'Omega Textiles Ltd')
            ->assertJsonPath('data.customers.0.due', '8000.0000')
            ->assertJsonPath('data.customers.1.company.name', 'Delta Apparels Ltd');
    }

    public function test_a_customer_who_was_never_invoiced_is_left_out(): void
    {
        TenantFixture::company('Never Billed Ltd', 'NBL');

        // A row of zeroes for every customer buries the ones that owe money.
        $this->asStaff()->getJson('/api/v1/platform/finance/summary')
            ->assertOk()
            ->assertJsonPath('data.customers', []);
    }

    public function test_a_closed_customer_who_still_owes_is_still_listed(): void
    {
        $this->invoice('INV-1', '4000.0000', paid: '0');

        $this->delta->delete();

        // Exactly the customer somebody opens this page to find — the row
        // itself is unaware the company is closed (`perCustomer()` reads
        // `withTrashed()`), which the direct DB check below confirms.
        $this->asStaff()->getJson('/api/v1/platform/finance/summary')
            ->assertOk()
            ->assertJsonCount(1, 'data.customers')
            ->assertJsonPath('data.customers.0.due', '4000.0000')
            ->assertJsonPath('data.customers.0.company.name', 'Delta Apparels Ltd');

        $this->assertTrue(
            Company::withoutGlobalScope(TenantScope::class)->withTrashed()->find($this->delta->id)->trashed(),
        );
    }

    public function test_an_expense_can_be_removed_from_the_totals(): void
    {
        $this->asStaff()->postJson('/api/v1/platform/finance/expenses', [
            'spent_on' => now()->toDateString(),
            'category' => 'DOMAIN',
            'description' => 'Domain renewal',
            'amount' => '1200',
            'currency' => 'BDT',
        ])->assertCreated();

        $expense = PlatformExpense::firstOrFail();

        $this->asStaff()
            ->deleteJson('/api/v1/platform/finance/expenses/'.$expense->id)
            ->assertNoContent();

        $this->assertSame(0, PlatformExpense::count());
    }

    public function test_an_expense_needs_a_known_category_and_a_positive_amount(): void
    {
        $this->asStaff()
            ->postJson('/api/v1/platform/finance/expenses', [
                'spent_on' => now()->toDateString(),
                // A free-text category would give "Hosting", "hosting" and
                // "AWS hosting" three rows in a summary meant to be added up.
                'category' => 'SALARIES',
                'description' => 'Something',
                'amount' => '0',
                'currency' => 'BDT',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category', 'amount']);

        $this->assertSame(0, PlatformExpense::count());
    }

    public function test_the_expense_categories_hold_no_payroll(): void
    {
        // Payroll is out of scope for this product by an explicit decision,
        // and an expense category is exactly how it would arrive anyway.
        foreach (PlatformExpense::CATEGORIES as $category) {
            $this->assertStringNotContainsStringIgnoringCase('salar', $category);
            $this->assertStringNotContainsStringIgnoringCase('wage', $category);
            $this->assertStringNotContainsStringIgnoringCase('payroll', $category);
        }
    }

    public function test_a_customer_cannot_see_the_money_page(): void
    {
        TenantFixture::actingAsTenant($this->delta);
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $token = app(\App\Modules\Api\Actions\IssueApiToken::class)->forUser($owner, $this->delta->id, 'Owner device')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/platform/finance/summary')
            ->assertNotFound();
    }

    public function test_overdue_invoices_are_singled_out(): void
    {
        // Past its due date and still owing. "Unpaid" is not the same fact: an
        // invoice inside its due date is business as usual, and one past it is
        // a phone call somebody has to make.
        $this->invoice('INV-LATE', '3000.0000', paid: '0', dueDate: now()->subDays(10)->toDateString());
        $this->invoice('INV-OK', '2000.0000', paid: '0', dueDate: now()->addDays(10)->toDateString());

        $this->asStaff()->getJson('/api/v1/platform/finance/invoices/overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_number', 'INV-LATE');
    }

    public function test_a_paid_invoice_is_never_overdue(): void
    {
        $this->invoice('INV-PAID', '3000.0000', paid: '3000.0000', dueDate: now()->subDays(30)->toDateString(), status: 'PAID');

        $this->asStaff()->getJson('/api/v1/platform/finance/invoices/overdue')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_every_payment_is_listed_one_row_each(): void
    {
        $this->invoice('INV-1', '10000.0000', paid: '10000.0000');
        $this->invoice('INV-2', '2000.0000', paid: '2000.0000');

        // The totals answer "how much came in"; this answers "which payments",
        // which is what somebody reconciles a bank statement against.
        $this->asStaff()->getJson('/api/v1/platform/finance/payments')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    private function asStaff(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->staffToken);

        return $this;
    }

    private function invoice(
        string $number,
        string $total,
        string $paid,
        ?Company $company = null,
        string $currency = 'BDT',
        string $status = 'ISSUED',
        ?string $dueDate = null,
    ): void {
        $company ??= $this->delta;

        $contract = SubscriptionContract::withoutGlobalScope(TenantScope::class)->create([
            'company_id' => $company->id,
            'contract_number' => 'SUB-'.$number,
            'start_date' => now()->startOfMonth()->toDateString(),
            'billing_cycle' => 'MONTHLY',
            'amount' => $total,
            'currency' => $currency,
            'grace_period_days' => 14,
            'status' => 'ACTIVE',
            'overage_policy' => 'WARN_ONLY',
            'auto_renew' => true,
        ]);

        $invoice = SubscriptionInvoice::withoutGlobalScope(TenantScope::class)->create([
            'company_id' => $company->id,
            'subscription_contract_id' => $contract->id,
            'invoice_number' => $number,
            'issue_date' => now()->toDateString(),
            'due_date' => $dueDate ?? now()->addDays(14)->toDateString(),
            'subtotal' => $total,
            'tax' => '0',
            'total' => $total,
            'currency' => $currency,
            'status' => $status,
            'paid_amount' => $paid,
            'balance_due' => bcsub($total, $paid, 4),
        ]);

        if (bccomp($paid, '0', 4) > 0) {
            SubscriptionPayment::withoutGlobalScope(TenantScope::class)->create([
                'company_id' => $company->id,
                'invoice_id' => $invoice->id,
                'payment_reference' => 'PAY-'.$number,
                'method' => 'BANK_TRANSFER',
                'amount' => $paid,
                'currency' => $currency,
                'paid_at' => now(),
                'status' => 'RECEIVED',
            ]);
        }
    }
}
