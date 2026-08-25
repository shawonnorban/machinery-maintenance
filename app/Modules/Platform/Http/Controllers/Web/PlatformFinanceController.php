<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Web;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Billing\Models\PlatformExpense;
use App\Modules\Billing\Models\Refund;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Billing\Models\SubscriptionPayment;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Scopes\TenantScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What the business took, what it is owed, and what it spent.
 *
 * Every figure here is read back from rows that already existed —
 * subscription_invoices, subscription_payments, refunds — rather than kept as
 * a running total somewhere. A stored total is a number that can drift from
 * the documents it claims to summarise, and the first time it does, nobody can
 * tell which of the two is wrong.
 *
 * Nothing is summed across currencies. A customer billed in BDT and one billed
 * in USD do not add up to anything, and a single figure that pretends they do
 * is worse than two figures — so the totals are grouped by currency and the
 * page shows one set per currency it finds.
 */
class PlatformFinanceController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(): View
    {
        $companies = Company::withoutGlobalScope(TenantScope::class)
            ->withTrashed()
            ->get(['id', 'name', 'code'])
            ->keyBy('id');

        return view('platform::desk.finance', [
            'totals' => $this->totalsByCurrency(),
            'customers' => $this->perCustomer(),
            'monthly' => $this->monthlyMovement(),
            'expenses' => PlatformExpense::with('recorder:id,name')
                ->orderByDesc('spent_on')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(),
            'byCategory' => $this->spendByCategory(),

            // The literal answer to "how much has come in": every payment, one
            // row each. The per-customer table above gives the totals; this is
            // what somebody reconciles a bank statement against.
            'payments' => SubscriptionPayment::withoutGlobalScope(TenantScope::class)
                ->where('status', 'RECEIVED')
                ->orderByDesc('paid_at')
                ->limit(50)
                ->get(),

            // What is late, rather than merely unpaid. An invoice inside its
            // due date is a normal state of affairs; one past it is a phone
            // call somebody has to make.
            'overdue' => SubscriptionInvoice::withoutGlobalScope(TenantScope::class)
                ->whereNotIn('status', ['DRAFT', 'VOID', 'PAID'])
                ->where('balance_due', '>', 0)
                ->whereDate('due_date', '<', now()->toDateString())
                ->orderBy('due_date')
                ->limit(50)
                ->get(),

            // Both lists name a customer, and neither belongs to one, so the
            // names are resolved once here rather than with a query per row.
            'companies' => $companies,
        ]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'spent_on' => ['required', 'date'],
            'category' => ['required', 'in:'.implode(',', PlatformExpense::CATEGORIES)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:64'],
        ]);

        // Uppercased in $data, not appended after it. PHP's + keeps the
        // left-hand value when a key exists on both sides, so a 'currency'
        // added on the right was silently discarded and "bdt" was stored as
        // typed — which then grouped as a second currency that never added up
        // to BDT.
        $data['currency'] = strtoupper($data['currency']);

        $expense = PlatformExpense::create($data + ['recorded_by' => $request->user()->id]);

        $this->audit->event(
            'CREATED',
            ['reason' => 'PLATFORM_EXPENSE_RECORDED', 'amount' => $data['amount'], 'category' => $data['category']],
            userId: $request->user()->id,
            label: $expense->description,
        );

        return back()->with('status', __('platform.expense_recorded'));
    }

    public function destroyExpense(Request $request, string $expense): RedirectResponse
    {
        $target = PlatformExpense::findOrFail($expense);

        $description = $target->description;

        $target->delete();

        $this->audit->event(
            'DELETED',
            ['reason' => 'PLATFORM_EXPENSE_REMOVED'],
            userId: $request->user()->id,
            label: $description,
        );

        return back()->with('status', __('platform.expense_removed'));
    }

    /**
     * Invoiced, received, outstanding and spent — per currency.
     *
     * @return array<string, array<string, string>>
     */
    private function totalsByCurrency(): array
    {
        $rows = [];

        // Voided invoices are excluded from everything: a voided invoice is a
        // document that was withdrawn, and counting it as billed would make
        // the business look owed money nobody owes.
        $invoiced = SubscriptionInvoice::withoutGlobalScope(TenantScope::class)
            ->whereNotIn('status', ['DRAFT', 'VOID'])
            ->groupBy('currency')
            ->selectRaw('currency, SUM(total) as billed, SUM(balance_due) as due')
            ->get();

        foreach ($invoiced as $row) {
            $rows[$row->currency]['invoiced'] = (string) $row->billed;
            $rows[$row->currency]['due'] = (string) $row->due;
        }

        $received = SubscriptionPayment::withoutGlobalScope(TenantScope::class)
            ->where('status', 'RECEIVED')
            ->groupBy('currency')
            ->selectRaw('currency, SUM(amount) as taken')
            ->get();

        foreach ($received as $row) {
            $rows[$row->currency]['received'] = (string) $row->taken;
        }

        // Money given back is money the business does not have, so it comes
        // off what was received rather than sitting in a column of its own
        // that somebody has to remember to subtract.
        $refunded = Refund::withoutGlobalScope(TenantScope::class)
            ->whereIn('status', ['ISSUED', 'SETTLED'])
            ->groupBy('currency')
            ->selectRaw('currency, SUM(amount) as given_back')
            ->get();

        foreach ($refunded as $row) {
            $rows[$row->currency]['refunded'] = (string) $row->given_back;
        }

        $spent = PlatformExpense::query()
            ->groupBy('currency')
            ->selectRaw('currency, SUM(amount) as spent')
            ->get();

        foreach ($spent as $row) {
            $rows[$row->currency]['spent'] = (string) $row->spent;
        }

        foreach ($rows as $currency => $figures) {
            $received = $figures['received'] ?? '0';
            $refunded = $figures['refunded'] ?? '0';
            $spent = $figures['spent'] ?? '0';

            // bcmath, not floats: these are DECIMAL(18,4) columns and the
            // whole point of that choice is that the arithmetic is exact
            // (ADR-063).
            $net = bcsub(bcsub($received, $refunded, 4), $spent, 4);

            $rows[$currency] = [
                'invoiced' => $figures['invoiced'] ?? '0',
                'due' => $figures['due'] ?? '0',
                'received' => $received,
                'refunded' => $refunded,
                'spent' => $spent,
                'net' => $net,
            ];
        }

        ksort($rows);

        return $rows;
    }

    /**
     * What each customer has been billed, has paid, and still owes.
     *
     * @return list<array<string, mixed>>
     */
    private function perCustomer(): array
    {
        $invoices = SubscriptionInvoice::withoutGlobalScope(TenantScope::class)
            ->whereNotIn('status', ['DRAFT', 'VOID'])
            ->groupBy('company_id', 'currency')
            ->selectRaw('company_id, currency, SUM(total) as billed, SUM(balance_due) as due, COUNT(*) as invoices')
            ->get()
            ->keyBy('company_id');

        $payments = SubscriptionPayment::withoutGlobalScope(TenantScope::class)
            ->where('status', 'RECEIVED')
            ->groupBy('company_id')
            ->selectRaw('company_id, SUM(amount) as taken, MAX(paid_at) as last_paid')
            ->get()
            ->keyBy('company_id');

        // withTrashed: a closed customer who still owes money is exactly the
        // customer somebody opening this page is looking for.
        $companies = Company::withoutGlobalScope(TenantScope::class)
            ->withTrashed()
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($companies as $company) {
            $invoice = $invoices[$company->id] ?? null;
            $payment = $payments[$company->id] ?? null;

            // A customer who has never been invoiced has nothing to show on a
            // money page, and a row of zeroes for each of them buries the
            // ones that do.
            if ($invoice === null && $payment === null) {
                continue;
            }

            $rows[] = [
                'company' => $company,
                'currency' => $invoice->currency ?? $company->base_currency,
                'invoiced' => (string) ($invoice->billed ?? '0'),
                'due' => (string) ($invoice->due ?? '0'),
                'received' => (string) ($payment->taken ?? '0'),
                'invoices' => (int) ($invoice->invoices ?? 0),
                'last_paid' => $payment->last_paid ?? null,
            ];
        }

        // Whoever owes the most, first. That is the reason to open this table.
        usort($rows, fn (array $a, array $b): int => bccomp($b['due'], $a['due'], 4));

        return $rows;
    }

    /**
     * Received and spent, month by month, for the last twelve.
     *
     * @return array{received: array<string, float>, spent: array<string, float>}
     */
    private function monthlyMovement(): array
    {
        $since = now()->subMonths(11)->startOfMonth();

        $received = SubscriptionPayment::withoutGlobalScope(TenantScope::class)
            ->where('status', 'RECEIVED')
            ->where('paid_at', '>=', $since)
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $spent = PlatformExpense::query()
            ->where('spent_on', '>=', $since)
            ->selectRaw("DATE_FORMAT(spent_on, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $out = ['received' => [], 'spent' => []];

        for ($i = 11; $i >= 0; $i--) {
            $cursor = now()->subMonths($i);
            $key = $cursor->format('Y-m');
            $label = $cursor->format('M Y');

            $out['received'][$label] = (float) ($received[$key] ?? 0);
            $out['spent'][$label] = (float) ($spent[$key] ?? 0);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function spendByCategory(): array
    {
        return PlatformExpense::query()
            ->groupBy('category', 'currency')
            ->selectRaw('category, currency, SUM(amount) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'category' => $row->category,
                'currency' => $row->currency,
                'total' => (string) $row->total,
            ])
            ->all();
    }
}
