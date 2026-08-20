<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Web;

use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Billing\Models\SubscriptionPayment;
use App\Modules\Billing\Services\PaymentRecorder;
use App\Modules\Billing\Services\UsageMeter;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * What the customer owes and what they are using (SRS 40).
 *
 * Reachable even when the subscription is read-only, because the page where
 * somebody would settle the account is the last page that should be locked.
 */
class BillingController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly UsageMeter $meter,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeBilling($request);

        $contract = $this->contract();

        return view('billing::billing.index', [
            'contract' => $contract,
            'invoices' => SubscriptionInvoice::query()
                ->when($contract !== null, fn ($q) => $q->where('subscription_contract_id', $contract->id))
                ->orderByDesc('issue_date')
                ->limit(24)
                ->get(),
            'usage' => $this->meter->latestFor($this->context->companyId()),
            'outstanding' => $this->outstanding($contract),
        ]);
    }

    public function show(Request $request, SubscriptionInvoice $invoice): View
    {
        $this->authorizeBilling($request);

        if ($invoice->company_id !== $this->context->companyId()) {
            throw new NotFoundHttpException;
        }

        return view('billing::billing.invoice', [
            'invoice' => $invoice->load(['lines', 'payments.recorder', 'creditNotes', 'contract']),
            'methods' => SubscriptionPayment::METHODS,
        ]);
    }

    /**
     * Record money received (SRS 40).
     *
     * A separate permission from managing the subscription: the person who
     * signs the contract and the person who confirms a bank transfer arrived
     * are rarely the same person.
     */
    public function pay(Request $request, SubscriptionInvoice $invoice, PaymentRecorder $recorder): RedirectResponse
    {
        if (! $request->user()->can('billing.payment.manage')) {
            abort(403);
        }

        if ($invoice->company_id !== $this->context->companyId()) {
            throw new NotFoundHttpException;
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.0001'],
            'method' => ['required', Rule::in(SubscriptionPayment::METHODS)],
            'payment_reference' => ['nullable', 'string', 'max:64'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $recorder->record($invoice, $data, $request->user()->id);

        return redirect()
            ->route('app.billing.invoice', $invoice)
            ->with('status', __('billing.payment_recorded'));
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

        $invoices = SubscriptionInvoice::query()
            ->where('subscription_contract_id', $contract->id)
            ->whereIn('status', SubscriptionInvoice::OPEN_STATUSES)
            ->get();

        foreach ($invoices as $invoice) {
            $total = bcadd($total, (string) $invoice->balance_due, 4);
        }

        return $total;
    }

    private function authorizeBilling(Request $request): void
    {
        if (! $request->user()->can('billing.subscription.manage')
            && ! $request->user()->can('billing.payment.manage')) {
            abort(403);
        }
    }
}
