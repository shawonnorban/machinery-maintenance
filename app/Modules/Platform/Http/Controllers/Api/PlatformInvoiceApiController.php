<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Billing\Services\InvoiceBuilder;
use App\Modules\Billing\Services\PaymentRecorder;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Invoicing a customer, from the side that sends the invoice (SRS 40;
 * mirrors `TenantBillingController`, which this delegates every write to
 * unchanged per ADR-003 — added here because the web screen had this whole
 * lifecycle and the platform API never did, which meant Phase F would have
 * decommissioned Blade's billing tab with nothing behind it).
 *
 * `InvoiceBuilder`/`PaymentRecorder` both read the numbering sequence from
 * `TenantContext`, which has no company outside `/app/*` — `asTenant()`
 * resolves the customer as the tenant for the duration of the call, exactly
 * as the web controller does, and puts the platform's own (tenant-less)
 * context back afterwards whatever happens.
 */
class PlatformInvoiceApiController extends ApiController
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly TenantContext $context,
    ) {}

    public function index(Company $company): JsonResponse
    {
        $invoices = SubscriptionInvoice::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->orderByDesc('issue_date')
            ->orderByDesc('invoice_number')
            ->limit(24)
            ->get();

        return ApiResponse::ok($invoices->map(fn (SubscriptionInvoice $i): array => $this->summary($i))->all());
    }

    /**
     * Draft, not issue. A draft can be corrected; an issued invoice is a
     * document somebody has been sent, and its totals do not move
     * afterwards — so the two steps stay separate.
     */
    public function store(Request $request, Company $company, InvoiceBuilder $invoices): JsonResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $contract = $this->currentContract($company);

        return $this->asTenant($company, function () use ($invoices, $contract, $data, $company): JsonResponse {
            $invoice = $invoices->draft(
                $contract,
                CarbonImmutable::parse($data['period_start']),
                CarbonImmutable::parse($data['period_end']),
                (string) ($data['tax_rate'] ?? '0'),
            );

            $this->record($company, 'INVOICE_DRAFTED', ['invoice' => $invoice->invoice_number]);

            return ApiResponse::created($this->summary($invoice));
        });
    }

    public function issue(Request $request, string $invoiceId, InvoiceBuilder $invoices): JsonResponse
    {
        $invoice = $this->invoice($invoiceId);
        $company = $this->companyFor($invoice);

        return $this->asTenant($company, function () use ($invoices, $invoice, $company): JsonResponse {
            $issued = $invoices->issue($invoice);

            $this->record($company, 'INVOICE_ISSUED', ['invoice' => $issued->invoice_number]);

            return ApiResponse::ok($this->summary($issued));
        });
    }

    public function pay(Request $request, string $invoiceId, PaymentRecorder $payments): JsonResponse
    {
        $invoice = $this->invoice($invoiceId);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'max:32'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $company = $this->companyFor($invoice);

        return $this->asTenant($company, function () use ($payments, $invoice, $data, $company): JsonResponse {
            $payments->record($invoice, $data, request()->user()->id);

            $this->record($company, 'INVOICE_PAYMENT_RECORDED', [
                'invoice' => $invoice->invoice_number,
                'amount' => $data['amount'],
            ]);

            return ApiResponse::created($this->summary($invoice->fresh()));
        });
    }

    /**
     * Void an invoice that should never have been sent — voided rather than
     * deleted, with the reason kept, so an invoice number never simply
     * disappears.
     */
    public function void(Request $request, string $invoiceId, PaymentRecorder $payments): JsonResponse
    {
        $invoice = $this->invoice($invoiceId);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $company = $this->companyFor($invoice);

        return $this->asTenant($company, function () use ($payments, $invoice, $data, $company): JsonResponse {
            $payments->void($invoice, $data['reason']);

            $this->record($company, 'INVOICE_VOIDED', [
                'invoice' => $invoice->invoice_number,
                'stated_reason' => $data['reason'],
            ]);

            return ApiResponse::ok($this->summary($invoice->fresh()));
        });
    }

    private function currentContract(Company $company): SubscriptionContract
    {
        $contract = SubscriptionContract::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->whereIn('status', ['ACTIVE', 'TRIAL', 'PAST_DUE', 'GRACE', 'READ_ONLY'])
            ->orderByDesc('start_date')
            ->first();

        if ($contract === null) {
            throw ValidationException::withMessages([
                'period_start' => __('platform.no_contract_to_invoice'),
            ]);
        }

        return $contract;
    }

    private function invoice(string $id): SubscriptionInvoice
    {
        return SubscriptionInvoice::withoutGlobalScope(TenantScope::class)->findOrFail($id);
    }

    private function companyFor(SubscriptionInvoice $invoice): Company
    {
        return Company::withoutGlobalScope(TenantScope::class)->findOrFail($invoice->company_id);
    }

    private function asTenant(Company $company, callable $work): JsonResponse
    {
        $restore = $this->context->companyIdOrNull();
        $this->context->set($company->id);

        try {
            return $work();
        } finally {
            $this->context->forget();

            if ($restore !== null) {
                $this->context->set($restore);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function record(Company $company, string $label, array $details): void
    {
        $this->audit->event(
            'SUBSCRIPTION_CHANGED',
            $details + ['company_id' => $company->id],
            userId: request()->user()->id,
            label: $label,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(SubscriptionInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'company_id' => $invoice->company_id,
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
}
