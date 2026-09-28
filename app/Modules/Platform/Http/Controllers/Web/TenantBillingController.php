<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Web;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Billing\Services\InvoiceBuilder;
use App\Modules\Billing\Services\PaymentRecorder;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Invoicing a customer, from the side that sends the invoice (SRS 40).
 *
 * The machinery already existed and only the customer's own screen could see
 * it: a company could read its invoices and record a payment against one, and
 * nobody could raise one. `billing:advance` issues them on the cycle, which
 * covers the normal month and none of the exceptions — the first invoice, a
 * mid-term adjustment, an invoice raised early because a customer asked.
 *
 * Everything here goes through the same services the scheduled command uses,
 * so an invoice raised by hand and one raised at 2am are the same kind of
 * document (ADR-066).
 */
class TenantBillingController extends Controller
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly TenantContext $context,
    ) {}

    /**
     * Raise an invoice for one period.
     *
     * Drafted, not issued. A draft can be corrected; an issued invoice is a
     * document somebody has been sent, and its totals do not move afterwards
     * — so the two steps are separate and the second one is deliberate.
     */
    public function store(Request $request, Company $company, InvoiceBuilder $invoices): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $contract = $this->currentContract($company);

        // Billing writes tenant-owned rows and the numbering sequence reads the
        // company from context, so it has to be the customer's rather than
        // nothing — the platform area has no tenant of its own.
        return $this->asTenant($company, function () use ($invoices, $contract, $data, $company): RedirectResponse {
            $invoice = $invoices->draft(
                $contract,
                CarbonImmutable::parse($data['period_start']),
                CarbonImmutable::parse($data['period_end']),
                (string) ($data['tax_rate'] ?? '0'),
            );

            $this->record($company, 'INVOICE_DRAFTED', ['invoice' => $invoice->invoice_number]);

            return back()->with('status', __('platform.invoice_drafted', [
                'number' => $invoice->invoice_number,
            ]));
        });
    }

    /**
     * Issue a draft, which is the point of no return for its totals.
     */
    public function issue(Request $request, SubscriptionInvoice $invoice, InvoiceBuilder $invoices): RedirectResponse
    {
        $company = $this->companyFor($invoice);

        return $this->asTenant($company, function () use ($invoices, $invoice, $company): RedirectResponse {
            $issued = $invoices->issue($invoice);

            $this->record($company, 'INVOICE_ISSUED', ['invoice' => $issued->invoice_number]);

            return back()->with('status', __('platform.invoice_issued', [
                'number' => $issued->invoice_number,
            ]));
        });
    }

    /**
     * Record a payment against an invoice.
     *
     * Here as well as on the customer's screen, because most payments in this
     * market arrive as a bank transfer somebody in the office reconciles — not
     * as a card the customer enters themselves.
     */
    public function pay(Request $request, SubscriptionInvoice $invoice, PaymentRecorder $payments): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'max:32'],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $company = $this->companyFor($invoice);

        return $this->asTenant($company, function () use ($payments, $invoice, $data, $company): RedirectResponse {
            $payments->record($invoice, $data, request()->user()->id);

            $this->record($company, 'INVOICE_PAYMENT_RECORDED', [
                'invoice' => $invoice->invoice_number,
                'amount' => $data['amount'],
            ]);

            return back()->with('status', __('platform.payment_recorded'));
        });
    }

    /**
     * Void an invoice that should never have been sent.
     *
     * Voided rather than deleted, with the reason kept: an invoice number that
     * simply disappears is a gap somebody will have to explain to an auditor.
     */
    public function void(Request $request, SubscriptionInvoice $invoice, PaymentRecorder $payments): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $company = $this->companyFor($invoice);

        return $this->asTenant($company, function () use ($payments, $invoice, $data, $company): RedirectResponse {
            $payments->void($invoice, $data['reason']);

            $this->record($company, 'INVOICE_VOIDED', [
                'invoice' => $invoice->invoice_number,
                'stated_reason' => $data['reason'],
            ]);

            return back()->with('status', __('platform.invoice_voided', [
                'number' => $invoice->invoice_number,
            ]));
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
            // Nothing to invoice against, and inventing terms for one invoice
            // would put a figure on a document with nothing behind it.
            throw ValidationException::withMessages([
                'period_start' => __('platform.no_contract_to_invoice'),
            ]);
        }

        return $contract;
    }

    private function companyFor(SubscriptionInvoice $invoice): Company
    {
        return Company::findOrFail($invoice->company_id);
    }

    /**
     * Run a callback with the customer as the resolved tenant, and put the
     * context back afterwards whatever happens.
     */
    private function asTenant(Company $company, callable $work): RedirectResponse
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
}
