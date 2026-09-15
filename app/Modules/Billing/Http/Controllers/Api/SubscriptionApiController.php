<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Api;

use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Billing\Models\SubscriptionPayment;
use App\Modules\Billing\Services\PaymentRecorder;
use App\Modules\Billing\Services\UsageMeter;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What the customer owes and what they are using, over the wire (API 25;
 * mirrors the web `BillingController`, which this delegates every write to
 * unchanged per ADR-003).
 *
 * Reachable even when the subscription itself is read-only, because the
 * endpoint where somebody would settle the account is the last one that
 * should be locked (SRS 40) — this controller sits outside
 * `EnforceSubscriptionState`'s write gate for that reason, same as the web
 * screen.
 *
 * `POST /subscription`, `PATCH /subscription`, `/cancel`, `/renew` and
 * `/refunds` from the v1.0 spec are deliberately not built here. A tenant
 * has no self-service action anywhere in this product for creating,
 * editing, cancelling or renewing its own contract, or for issuing a
 * refund — those are `SubscriptionLifecycle`/`PaymentRecorder` operations
 * platform staff perform (see `TenantBillingController`), and a company
 * granting itself the power to cancel its own billing contract is a
 * capability this system does not intend to offer, not a gap to fill.
 */
class SubscriptionApiController extends ApiController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly UsageMeter $meter,
    ) {}

    public function show(): JsonResponse
    {
        $this->allowBilling();

        $contract = $this->contract();

        return ApiResponse::ok([
            'contract' => $contract === null ? null : $this->contractSummary($contract),
            'outstanding' => $this->outstanding($contract),
            'usage' => $this->meter->latestFor($this->context->companyId())
                ->map(fn ($metric): array => [
                    'metric' => $metric->metric,
                    'value' => $metric->value,
                    'limit' => $metric->limit_value,
                    'exceeded' => (bool) $metric->exceeded,
                    'period_start' => $metric->period_start?->toDateString(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $this->allowBilling();

        $contract = $this->contract();

        $query = SubscriptionInvoice::query()
            ->when($contract !== null, fn ($q) => $q->where('subscription_contract_id', $contract->id))
            ->orderByDesc('issue_date');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (SubscriptionInvoice $invoice): array => $this->invoiceSummary($invoice),
        );
    }

    public function showInvoice(SubscriptionInvoice $invoice): JsonResponse
    {
        $this->allowBilling();
        $this->assertOwn($invoice);

        $invoice->load(['lines', 'payments', 'creditNotes']);

        return ApiResponse::ok($this->invoiceSummary($invoice) + [
            'lines' => $invoice->lines->map(fn ($line): array => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'amount' => $line->amount,
                'period_start' => $line->period_start?->toDateString(),
                'period_end' => $line->period_end?->toDateString(),
            ])->all(),
            'payments' => $invoice->payments->map(fn (SubscriptionPayment $p): array => $this->paymentSummary($p))->all(),
            'credit_notes' => $invoice->creditNotes->map(fn ($n): array => [
                'id' => $n->id,
                'credit_note_number' => $n->credit_note_number,
                'amount' => $n->amount,
                'reason' => $n->reason,
                'status' => $n->status,
                'issued_at' => $n->issued_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * All payments across every invoice on the current contract, most
     * recent first — the ledger a finance person reconciles against a bank
     * statement.
     */
    public function payments(Request $request): JsonResponse
    {
        $this->allowBilling();

        $contract = $this->contract();

        $query = SubscriptionPayment::query()
            ->when($contract !== null, fn ($q) => $q->whereIn(
                'invoice_id',
                SubscriptionInvoice::where('subscription_contract_id', $contract->id)->select('id'),
            ))
            ->when($contract === null, fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByDesc('paid_at');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (SubscriptionPayment $payment): array => $this->paymentSummary($payment),
        );
    }

    /**
     * Record money received against an invoice (SRS 40). A separate
     * permission from managing the subscription: the person who signs the
     * contract and the person who confirms a bank transfer arrived are
     * rarely the same person.
     */
    public function storePayment(Request $request, SubscriptionInvoice $invoice, PaymentRecorder $recorder): JsonResponse
    {
        if (! $this->caller()->can('billing.payment.manage')) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        $this->assertOwn($invoice);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.0001'],
            'method' => ['required', Rule::in(SubscriptionPayment::METHODS)],
            'payment_reference' => ['nullable', 'string', 'max:64'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = $recorder->record($invoice, $data, $this->caller()->auditUserId());

        return ApiResponse::created($this->paymentSummary($payment));
    }

    private function contract(): ?SubscriptionContract
    {
        return SubscriptionContract::query()->orderByDesc('start_date')->first();
    }

    private function outstanding(?SubscriptionContract $contract): string
    {
        if ($contract === null) {
            return '0.0000';
        }

        $total = '0.0000';

        foreach (SubscriptionInvoice::where('subscription_contract_id', $contract->id)
            ->whereIn('status', SubscriptionInvoice::OPEN_STATUSES)
            ->get() as $invoice) {
            $total = bcadd($total, (string) $invoice->balance_due, 4);
        }

        return $total;
    }

    private function assertOwn(SubscriptionInvoice $invoice): void
    {
        if ($invoice->company_id !== $this->context->companyId()) {
            abort(404);
        }
    }

    private function allowBilling(): void
    {
        if (! $this->caller()->can('billing.subscription.manage') && ! $this->caller()->can('billing.payment.manage')) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function contractSummary(SubscriptionContract $contract): array
    {
        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'status' => $contract->status,
            'is_read_only' => $contract->isReadOnly(),
            'start_date' => $contract->start_date?->toDateString(),
            'end_date' => $contract->end_date?->toDateString(),
            'billing_cycle' => $contract->billing_cycle,
            'amount' => $contract->amount,
            'currency' => $contract->currency,
            'trial_end' => $contract->trial_end?->toDateString(),
            'auto_renew' => $contract->auto_renew,
            'overage_policy' => $contract->overage_policy,
            'grace_period_days' => $contract->grace_period_days,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceSummary(SubscriptionInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'is_overdue' => $invoice->isOverdue(),
            'subtotal' => $invoice->subtotal,
            'tax' => $invoice->tax,
            'total' => $invoice->total,
            'paid_amount' => $invoice->paid_amount,
            'balance_due' => $invoice->balance_due,
            'currency' => $invoice->currency,
            'void_reason' => $invoice->void_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentSummary(SubscriptionPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'payment_reference' => $payment->payment_reference,
            'method' => $payment->method,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ];
    }
}
