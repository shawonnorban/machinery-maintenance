<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Billing\Models\SubscriptionInvoiceLine;
use App\Modules\Billing\Models\UsageMetric;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a contract and a period into an itemized bill (SRS 40, ERD Section 19).
 *
 * Every figure is bcmath on decimal strings. Floating point arithmetic on money
 * produces totals that are out by a hundredth, and an invoice that is out by a
 * hundredth is an invoice somebody disputes (ADR-063).
 *
 * A draft can be rebuilt as often as anybody likes. Once issued it is frozen:
 * corrections are a credit note, or a void and a reissue.
 */
class InvoiceBuilder
{
    private const SCALE = 4;

    public function __construct(private readonly NumberSequenceGenerator $numbers) {}

    /**
     * Build a draft invoice for one billing period.
     */
    public function draft(
        SubscriptionContract $contract,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        string $taxRate = '0',
    ): SubscriptionInvoice {
        if (in_array($contract->status, ['DRAFT', 'CANCELLED', 'ARCHIVED'], true)) {
            throw ValidationException::withMessages([
                'contract' => __('billing.cannot_invoice_status', ['status' => $contract->status]),
            ])->status(409);
        }

        return DB::transaction(function () use ($contract, $periodStart, $periodEnd, $taxRate): SubscriptionInvoice {
            $invoice = SubscriptionInvoice::create([
                'subscription_contract_id' => $contract->id,
                'invoice_number' => $this->numbers->next('INVOICE'),
                'issue_date' => CarbonImmutable::now()->toDateString(),
                // Fourteen days unless the contract says otherwise; a due date
                // is what the grace period is measured from, so it is never
                // left null.
                'due_date' => CarbonImmutable::now()->addDays(14)->toDateString(),
                'currency' => $contract->currency,
                'tax_rate' => $taxRate,
                'status' => 'DRAFT',
            ]);

            $lines = $this->linesFor($contract, $periodStart, $periodEnd);

            foreach ($lines as $index => $line) {
                SubscriptionInvoiceLine::create([
                    'subscription_invoice_id' => $invoice->id,
                    'description' => $line['description'],
                    'metric' => $line['metric'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'amount' => $line['amount'],
                    'tax_rate' => $taxRate,
                    'tax_amount' => $this->percentage($line['amount'], $taxRate),
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'sort_order' => $index,
                ]);
            }

            return $this->recalculate($invoice->fresh());
        });
    }

    /**
     * Recompute a draft's totals from its lines.
     *
     * Refuses on anything issued: the totals on a document a customer has seen
     * do not move.
     */
    public function recalculate(SubscriptionInvoice $invoice): SubscriptionInvoice
    {
        if ($invoice->isImmutable()) {
            throw ValidationException::withMessages([
                'invoice' => __('billing.invoice_immutable'),
            ])->status(409);
        }

        $subtotal = '0.0000';
        $tax = '0.0000';

        foreach ($invoice->lines as $line) {
            $subtotal = bcadd($subtotal, (string) $line->amount, self::SCALE);
            $tax = bcadd($tax, (string) $line->tax_amount, self::SCALE);
        }

        $total = bcadd($subtotal, $tax, self::SCALE);

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'balance_due' => bcsub($total, (string) $invoice->paid_amount, self::SCALE),
        ])->save();

        return $invoice->fresh();
    }

    /**
     * Issue the invoice, after which its figures are fixed.
     */
    public function issue(SubscriptionInvoice $invoice): SubscriptionInvoice
    {
        if ($invoice->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'invoice' => __('billing.only_drafts_issue'),
            ])->status(409);
        }

        if (bccomp((string) $invoice->total, '0', self::SCALE) <= 0) {
            // An invoice for nothing is a document somebody has to explain.
            throw ValidationException::withMessages([
                'invoice' => __('billing.invoice_empty'),
            ]);
        }

        $invoice->forceFill([
            'status' => 'ISSUED',
            'issue_date' => CarbonImmutable::now()->toDateString(),
            'balance_due' => bcsub((string) $invoice->total, (string) $invoice->paid_amount, self::SCALE),
        ])->save();

        return $invoice->fresh();
    }

    /**
     * The priced items for a period.
     *
     * A flat contract is one line. A metered one is a line per metric, priced
     * from what was measured rather than from what the salesperson assumed
     * (ADR-028).
     *
     * @return list<array{description: string, metric: string, quantity: string, unit_price: string, amount: string}>
     */
    private function linesFor(
        SubscriptionContract $contract,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
    ): array {
        $pricing = $contract->pricing_model_json ?? [];

        if ($pricing === [] || ($pricing['type'] ?? 'FLAT') === 'FLAT') {
            return [[
                'description' => __('billing.line_subscription', [
                    'cycle' => __('billing.cycles.'.$contract->billing_cycle),
                ]),
                'metric' => 'FLAT',
                'quantity' => '1.0000',
                'unit_price' => (string) $contract->amount,
                'amount' => (string) $contract->amount,
            ]];
        }

        $lines = [];

        foreach (['FACTORIES' => 'ACTIVE_FACTORIES', 'ASSETS' => 'ACTIVE_ASSETS', 'USERS' => 'ACTIVE_USERS'] as $unit => $metric) {
            $rate = $pricing['rates'][$unit] ?? null;

            if ($rate === null) {
                continue;
            }

            $quantity = (string) (UsageMetric::query()
                ->where('company_id', $contract->company_id)
                ->where('metric', $metric)
                ->whereDate('period_start', '<=', $periodEnd->toDateString())
                ->orderByDesc('period_start')
                ->value('value') ?? '0');

            $lines[] = [
                'description' => __('billing.line_metered', ['unit' => __('billing.metrics.'.$metric)]),
                'metric' => $unit,
                'quantity' => $quantity,
                'unit_price' => (string) $rate,
                'amount' => bcmul($quantity, (string) $rate, self::SCALE),
            ];
        }

        if ($lines === []) {
            // A metered contract with no rates that match anything measured is
            // a configuration mistake, not a free month.
            throw ValidationException::withMessages([
                'contract' => __('billing.no_priceable_lines'),
            ]);
        }

        return $lines;
    }

    private function percentage(string $amount, string $rate): string
    {
        return bcdiv(bcmul($amount, $rate, self::SCALE + 2), '100', self::SCALE);
    }
}
