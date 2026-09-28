<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Billing\Models\PlatformExpense;
use App\Modules\Billing\Models\Refund;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Billing\Models\SubscriptionPayment;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Scopes\TenantScope;
use App\Shared\Support\Sql;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the business took, what it is owed, and what it spent (Platform API
 * §8; mirrors `PlatformFinanceController`, which this delegates every write
 * to unchanged per ADR-003).
 *
 * Every figure is re-derived from invoices, payments and refunds on each
 * call rather than kept as a running total — a stored total is a number
 * that can drift from the documents it claims to summarise (ADR-063) — and
 * nothing is summed across currencies, since a customer billed in BDT and
 * one billed in USD do not add up to anything.
 */
class PlatformFinanceApiController extends ApiController
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function summary(): JsonResponse
    {
        return ApiResponse::ok([
            'totals' => $this->totalsByCurrency(),
            'customers' => $this->perCustomer(),
            'monthly' => $this->monthlyMovement(),
            'by_category' => $this->spendByCategory(),
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $payments = SubscriptionPayment::withoutGlobalScope(TenantScope::class)
            ->where('status', 'RECEIVED')
            ->orderByDesc('paid_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($payments, fn (SubscriptionPayment $p): array => [
            'id' => $p->id,
            'company_id' => $p->company_id,
            'amount' => $p->amount,
            'currency' => $p->currency,
            'paid_at' => $p->paid_at?->toIso8601String(),
            'reference' => $p->reference,
        ]);
    }

    public function overdueInvoices(Request $request): JsonResponse
    {
        $invoices = SubscriptionInvoice::withoutGlobalScope(TenantScope::class)
            ->whereNotIn('status', ['DRAFT', 'VOID', 'PAID'])
            ->where('balance_due', '>', 0)
            ->whereDate('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($invoices, fn (SubscriptionInvoice $i): array => [
            'id' => $i->id,
            'company_id' => $i->company_id,
            'invoice_number' => $i->invoice_number,
            'status' => $i->status,
            'total' => $i->total,
            'balance_due' => $i->balance_due,
            'currency' => $i->currency,
            'due_date' => $i->due_date?->toDateString(),
        ]);
    }

    public function expenses(Request $request): JsonResponse
    {
        $expenses = PlatformExpense::with('recorder:id,name')
            ->orderByDesc('spent_on')
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($expenses, fn (PlatformExpense $e): array => $this->expenseSummary($e));
    }

    public function storeExpense(Request $request): JsonResponse
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

        $data['currency'] = strtoupper($data['currency']);

        $expense = PlatformExpense::create($data + ['recorded_by' => $request->user()->id]);

        $this->audit->event(
            'CREATED',
            ['reason' => 'PLATFORM_EXPENSE_RECORDED', 'amount' => $data['amount'], 'category' => $data['category']],
            userId: $request->user()->id,
            label: $expense->description,
        );

        return ApiResponse::created($this->expenseSummary($expense->load('recorder:id,name')));
    }

    public function destroyExpense(Request $request, string $expense): JsonResponse
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

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function totalsByCurrency(): array
    {
        $rows = [];

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

        $companies = Company::withoutGlobalScope(TenantScope::class)
            ->withTrashed()
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($companies as $company) {
            $invoice = $invoices[$company->id] ?? null;
            $payment = $payments[$company->id] ?? null;

            if ($invoice === null && $payment === null) {
                continue;
            }

            $rows[] = [
                'company' => ['id' => $company->id, 'name' => $company->name, 'code' => $company->code],
                'currency' => $invoice->currency ?? $company->base_currency,
                'invoiced' => (string) ($invoice->billed ?? '0'),
                'due' => (string) ($invoice->due ?? '0'),
                'received' => (string) ($payment->taken ?? '0'),
                'invoices' => (int) ($invoice->invoices ?? 0),
                'last_paid' => $payment->last_paid ?? null,
            ];
        }

        usort($rows, fn (array $a, array $b): int => bccomp($b['due'], $a['due'], 4));

        return $rows;
    }

    /**
     * @return array{received: array<string, float>, spent: array<string, float>}
     */
    private function monthlyMovement(): array
    {
        $since = now()->subMonths(11)->startOfMonth();

        $received = SubscriptionPayment::withoutGlobalScope(TenantScope::class)
            ->where('status', 'RECEIVED')
            ->where('paid_at', '>=', $since)
            ->selectRaw(Sql::monthBucket('paid_at').', SUM(amount) as total')
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $spent = PlatformExpense::query()
            ->where('spent_on', '>=', $since)
            ->selectRaw(Sql::monthBucket('spent_on').', SUM(amount) as total')
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

    /**
     * @return array<string, mixed>
     */
    private function expenseSummary(PlatformExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'spent_on' => $expense->spent_on?->toDateString(),
            'category' => $expense->category,
            'description' => $expense->description,
            'amount' => $expense->amount,
            'currency' => $expense->currency,
            'vendor' => $expense->vendor,
            'reference' => $expense->reference,
            'recorded_by' => $expense->relationLoaded('recorder') && $expense->recorder !== null
                ? ['id' => $expense->recorder->id, 'name' => $expense->recorder->name]
                : null,
        ];
    }
}
