<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\CreditNote;
use App\Modules\Billing\Models\Refund;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Billing\Models\SubscriptionPayment;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Money in, money back, and corrections (SRS 40).
 *
 * Payments are append-only. A mistyped receipt is reversed rather than edited,
 * because an invoice's paid amount is a figure two organisations have agreed
 * on, and changing one side of it silently is how reconciliation breaks.
 *
 * Partial payment is normal, not an edge case: these customers pay an invoice
 * across two or three transfers, and an invoice that only knows PAID and UNPAID
 * cannot tell anybody what is still outstanding.
 */
class PaymentRecorder
{
    private const SCALE = 4;

    public function __construct(private readonly NumberSequenceGenerator $numbers) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(SubscriptionInvoice $invoice, array $data, ?string $userId = null): SubscriptionPayment
    {
        if (! $invoice->isOpen()) {
            throw ValidationException::withMessages([
                'invoice' => __('billing.invoice_not_payable', ['status' => $invoice->status]),
            ])->status(409);
        }

        $amount = (string) ($data['amount'] ?? '0');

        if (bccomp($amount, '0', self::SCALE) <= 0) {
            throw ValidationException::withMessages(['amount' => __('billing.amount_positive')]);
        }

        if (bccomp($amount, (string) $invoice->balance_due, self::SCALE) > 0) {
            // Overpayment is a real thing, but it is a decision somebody has to
            // make deliberately — a credit, or a refund — not something a
            // payment form should absorb quietly.
            throw ValidationException::withMessages([
                'amount' => __('billing.amount_exceeds_balance', [
                    'balance' => $invoice->balance_due,
                ]),
            ]);
        }

        return DB::transaction(function () use ($invoice, $data, $amount, $userId): SubscriptionPayment {
            $payment = SubscriptionPayment::create([
                'invoice_id' => $invoice->id,
                'payment_reference' => $data['payment_reference'] ?? $this->numbers->next('INVOICE').'-P',
                'method' => $data['method'],
                'amount' => $amount,
                'currency' => $invoice->currency,
                'paid_at' => $data['paid_at'] ?? CarbonImmutable::now(),
                'status' => 'RECEIVED',
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $userId,
            ]);

            $this->settle($invoice->fresh());

            return $payment;
        });
    }

    /**
     * Undo a receipt that never actually arrived.
     */
    public function reverse(SubscriptionPayment $payment, string $reason, ?string $userId = null): SubscriptionPayment
    {
        if ($payment->status === 'REVERSED') {
            throw ValidationException::withMessages([
                'payment' => __('billing.already_reversed'),
            ])->status(409);
        }

        return DB::transaction(function () use ($payment, $reason, $userId): SubscriptionPayment {
            $payment->forceFill([
                'status' => 'REVERSED',
                'notes' => trim(($payment->notes ? $payment->notes."\n" : '').$reason),
            ])->save();

            $this->settle($payment->invoice->fresh());

            // The reversal leaves both rows visible: the receipt that was
            // recorded and the fact that it came back.
            Refund::create([
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'reason' => $reason,
                'status' => 'ISSUED',
                'issued_at' => CarbonImmutable::now(),
                'issued_by' => $userId,
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Correct an issued invoice without touching it (ERD Section 19).
     */
    public function creditNote(
        SubscriptionInvoice $invoice,
        string $amount,
        string $reason,
        ?string $userId = null,
    ): CreditNote {
        if (! $invoice->isImmutable()) {
            // A draft is corrected by editing it. A credit note against one
            // would be a document explaining a change nobody ever saw.
            throw ValidationException::withMessages([
                'invoice' => __('billing.credit_note_needs_issued'),
            ])->status(409);
        }

        if (bccomp($amount, '0', self::SCALE) <= 0 || bccomp($amount, (string) $invoice->total, self::SCALE) > 0) {
            throw ValidationException::withMessages([
                'amount' => __('billing.credit_note_amount'),
            ]);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => __('billing.reason_required')]);
        }

        return DB::transaction(function () use ($invoice, $amount, $reason, $userId): CreditNote {
            $note = CreditNote::create([
                'invoice_id' => $invoice->id,
                'credit_note_number' => $this->numbers->next('INVOICE').'-CN',
                'amount' => $amount,
                'currency' => $invoice->currency,
                'reason' => $reason,
                'status' => 'ISSUED',
                'issued_at' => CarbonImmutable::now(),
                'issued_by' => $userId,
            ]);

            $this->settle($invoice->fresh());

            return $note;
        });
    }

    /**
     * Void an issued invoice so it can be reissued.
     */
    public function void(SubscriptionInvoice $invoice, string $reason): SubscriptionInvoice
    {
        if ($invoice->status === 'PAID') {
            // Money has changed hands. Voiding would leave a payment attached
            // to a document that says it was never owed; that is a credit note
            // or a refund.
            throw ValidationException::withMessages([
                'invoice' => __('billing.cannot_void_paid'),
            ])->status(409);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => __('billing.reason_required')]);
        }

        $invoice->forceFill([
            'status' => 'VOID',
            'voided_at' => CarbonImmutable::now(),
            'void_reason' => $reason,
            'balance_due' => '0.0000',
        ])->save();

        return $invoice->fresh();
    }

    /**
     * Recompute what is still owed from the receipts and credits attached.
     *
     * Derived rather than incremented: a running total that drifts from the
     * rows behind it is a figure nobody can reconcile.
     */
    public function settle(SubscriptionInvoice $invoice): SubscriptionInvoice
    {
        if (in_array($invoice->status, ['VOID', 'WRITTEN_OFF', 'DRAFT'], true)) {
            return $invoice;
        }

        $paid = '0.0000';

        foreach ($invoice->payments()->where('status', 'RECEIVED')->get() as $payment) {
            $paid = bcadd($paid, (string) $payment->amount, self::SCALE);
        }

        foreach ($invoice->creditNotes()->whereIn('status', ['ISSUED', 'APPLIED'])->get() as $note) {
            // A credit note reduces what is owed exactly as a payment does; it
            // is simply money the customer never has to send.
            $paid = bcadd($paid, (string) $note->amount, self::SCALE);
        }

        $balance = bcsub((string) $invoice->total, $paid, self::SCALE);

        $status = match (true) {
            bccomp($balance, '0', self::SCALE) <= 0 => 'PAID',
            bccomp($paid, '0', self::SCALE) > 0 => 'PARTIALLY_PAID',
            $invoice->due_date->isPast() => 'OVERDUE',
            default => 'ISSUED',
        };

        $invoice->forceFill([
            'paid_amount' => $paid,
            'balance_due' => bccomp($balance, '0', self::SCALE) < 0 ? '0.0000' : $balance,
            'status' => $status,
            'paid_at' => $status === 'PAID' ? ($invoice->paid_at ?? CarbonImmutable::now()) : null,
        ])->save();

        return $invoice->fresh();
    }
}
