<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\ItemMaster\Services\ItemLineResolver;
use App\Modules\Procurement\Models\LetterOfCredit;
use App\Modules\Procurement\Models\LetterOfCreditAmendment;
use App\Modules\Procurement\Models\ProformaInvoice;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Shipment;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The paperwork an import runs on (Procurement spec §10).
 *
 * Three documents, one chain: the supplier's proforma invoice is what the
 * bank opens a letter of credit against, and the shipment sails against
 * both. Keeping them in one action is what lets the chain be checked —
 * an LC opened against a superseded PI, or a shipment hung on a cancelled
 * LC, are the mistakes that surface at the port rather than in the office.
 *
 * Two rules worth naming:
 *
 *  - **A PI is versioned, not edited.** The bank opened its LC against a
 *    specific document; overwriting it would leave the LC matching
 *    something that no longer says what it said.
 *  - **The original ETA is never overwritten.** The gap between what was
 *    first promised and what happened is the supplier's delivery record,
 *    and a field updated quietly every week holds no record at all.
 */
class ManageImportDocuments
{
    private const SCALE = 4;

    public function __construct(private readonly NumberSequenceGenerator $numbers) {}

    /**
     * @param  array<string, mixed>  $data  pi_no, pi_date?, amount, currency?, incoterm?, ports?, notes?
     */
    public function createProformaInvoice(PurchaseOrder $order, array $data, ?string $userId = null): ProformaInvoice
    {
        $piNo = trim((string) ($data['pi_no'] ?? ''));

        if ($piNo === '') {
            throw ValidationException::withMessages(['pi_no' => __('validation.required', ['attribute' => 'pi_no'])]);
        }

        $amount = ItemLineResolver::money($data['amount'] ?? 0);

        if (bccomp($amount, '0', self::SCALE) !== 1) {
            throw ValidationException::withMessages(['amount' => __('inventory.quantity_must_be_positive')]);
        }

        return ProformaInvoice::create([
            'purchase_order_id' => $order->id,
            'vendor_id' => $order->vendor_id,
            'pi_no' => $piNo,
            'pi_date' => $data['pi_date'] ?? CarbonImmutable::today(),
            'version' => 1,
            'amount' => $amount,
            'currency' => $data['currency'] ?? $order->currency ?? 'USD',
            'status' => 'DRAFT',
            'incoterm' => $data['incoterm'] ?? null,
            'port_of_loading' => $data['port_of_loading'] ?? null,
            'port_of_discharge' => $data['port_of_discharge'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ])->fresh();
    }

    public function confirmProformaInvoice(ProformaInvoice $invoice): ProformaInvoice
    {
        $this->assertPiTransition($invoice, 'CONFIRMED');

        $invoice->forceFill([
            'status' => 'CONFIRMED',
            'confirmed_at' => CarbonImmutable::now(),
        ])->save();

        return $invoice->fresh();
    }

    /**
     * A revised PI supersedes the old one rather than replacing its
     * contents — see the class note.
     *
     * @param  array<string, mixed>  $data
     */
    public function reviseProformaInvoice(ProformaInvoice $invoice, array $data, ?string $userId = null): ProformaInvoice
    {
        if (! $invoice->isLive()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.pi_not_revisable', ['status' => $invoice->status]),
            ])->status(409);
        }

        $order = PurchaseOrder::findOrFail($invoice->purchase_order_id);

        return DB::transaction(function () use ($invoice, $order, $data, $userId): ProformaInvoice {
            $revision = ProformaInvoice::create([
                'purchase_order_id' => $order->id,
                'vendor_id' => $invoice->vendor_id,
                'pi_no' => trim((string) ($data['pi_no'] ?? $invoice->pi_no)),
                'pi_date' => $data['pi_date'] ?? CarbonImmutable::today(),
                'version' => $invoice->version + 1,
                'supersedes_id' => $invoice->id,
                'amount' => isset($data['amount']) ? ItemLineResolver::money($data['amount']) : $invoice->amount,
                'currency' => $data['currency'] ?? $invoice->currency,
                'status' => 'CONFIRMED',
                'incoterm' => $data['incoterm'] ?? $invoice->incoterm,
                'port_of_loading' => $data['port_of_loading'] ?? $invoice->port_of_loading,
                'port_of_discharge' => $data['port_of_discharge'] ?? $invoice->port_of_discharge,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'confirmed_at' => CarbonImmutable::now(),
            ]);

            $invoice->forceFill([
                'status' => 'SUPERSEDED',
                'superseded_at' => CarbonImmutable::now(),
            ])->save();

            return $revision->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data  lc_no, bank_name, amount, lc_date?, expiry_date?, tenor_days?, notes?
     */
    public function openLetterOfCredit(PurchaseOrder $order, ?ProformaInvoice $invoice, array $data, ?string $userId = null): LetterOfCredit
    {
        if ($invoice !== null && ! $invoice->isLive()) {
            // An LC against a superseded PI is an LC matching a document the
            // supplier has already replaced.
            throw ValidationException::withMessages([
                'proforma_invoice_id' => __('procurement.lc_pi_not_live', ['status' => $invoice->status]),
            ])->status(409);
        }

        $amount = ItemLineResolver::money($data['amount'] ?? 0);

        if (bccomp($amount, '0', self::SCALE) !== 1) {
            throw ValidationException::withMessages(['amount' => __('inventory.quantity_must_be_positive')]);
        }

        return LetterOfCredit::create([
            'purchase_order_id' => $order->id,
            'proforma_invoice_id' => $invoice?->id,
            'vendor_id' => $order->vendor_id,
            'lc_no' => trim((string) $data['lc_no']),
            'lc_date' => $data['lc_date'] ?? CarbonImmutable::today(),
            'bank_name' => $data['bank_name'],
            'branch' => $data['branch'] ?? null,
            'amount' => $amount,
            'currency' => $data['currency'] ?? $invoice?->currency ?? $order->currency ?? 'USD',
            'expiry_date' => $data['expiry_date'] ?? null,
            'last_shipment_date' => $data['last_shipment_date'] ?? null,
            'tenor_days' => $data['tenor_days'] ?? null,
            'status' => 'DRAFT',
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ])->fresh();
    }

    public function markLetterOpened(LetterOfCredit $letter): LetterOfCredit
    {
        $this->assertLcTransition($letter, 'OPENED');

        $letter->forceFill([
            'status' => 'OPENED',
            'opened_at' => CarbonImmutable::now(),
        ])->save();

        return $letter->fresh();
    }

    /**
     * An amendment, kept as its own row with its bank charge and reason.
     *
     * @param  array<string, mixed>  $data  new_amount?, new_expiry_date?, bank_charge?, reason
     */
    public function amendLetterOfCredit(LetterOfCredit $letter, array $data, ?string $userId = null): LetterOfCredit
    {
        if (! $letter->isLive()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.lc_not_amendable', ['status' => $letter->status]),
            ])->status(409);
        }

        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('procurement.lc_amendment_needs_reason')]);
        }

        $newAmount = isset($data['new_amount'])
            ? ItemLineResolver::money($data['new_amount'])
            : (string) $letter->amount;

        if (bccomp($newAmount, '0', self::SCALE) !== 1) {
            throw ValidationException::withMessages(['new_amount' => __('inventory.quantity_must_be_positive')]);
        }

        return DB::transaction(function () use ($letter, $data, $reason, $newAmount, $userId): LetterOfCredit {
            $next = (int) $letter->amendments()->max('amendment_no') + 1;
            $newExpiry = $data['new_expiry_date'] ?? $letter->expiry_date;

            LetterOfCreditAmendment::create([
                'letter_of_credit_id' => $letter->id,
                'amendment_no' => $next,
                'amendment_date' => $data['amendment_date'] ?? CarbonImmutable::today(),
                'previous_amount' => $letter->amount,
                'new_amount' => $newAmount,
                'previous_expiry_date' => $letter->expiry_date,
                'new_expiry_date' => $newExpiry,
                'bank_charge' => ItemLineResolver::money($data['bank_charge'] ?? 0),
                'currency' => $letter->currency,
                'reason' => $reason,
                'amended_by' => $userId,
            ]);

            $letter->forceFill([
                'amount' => $newAmount,
                'expiry_date' => $newExpiry,
            ])->save();

            return $letter->fresh();
        });
    }

    public function closeLetterOfCredit(LetterOfCredit $letter): LetterOfCredit
    {
        $this->assertLcTransition($letter, 'CLOSED');

        $letter->forceFill([
            'status' => 'CLOSED',
            'closed_at' => CarbonImmutable::now(),
        ])->save();

        return $letter->fresh();
    }

    /**
     * @param  array<string, mixed>  $data  mode?, bl_awb_no?, container_no?, shipping_date?, eta?, ports?
     */
    public function createShipment(PurchaseOrder $order, array $data, ?string $userId = null): Shipment
    {
        $letter = $this->letterFor($data['letter_of_credit_id'] ?? null, $order);
        $invoice = $this->invoiceFor($data['proforma_invoice_id'] ?? null, $order);
        // Named by its letter of credit alone, a shipment still sails
        // against that letter's invoice — which is where its ports are.
        $invoice ??= $letter?->proforma_invoice_id === null
            ? null
            : ProformaInvoice::find($letter->proforma_invoice_id);

        $mode = strtoupper((string) ($data['mode'] ?? 'SEA'));

        if (! in_array($mode, Shipment::MODES, true)) {
            throw ValidationException::withMessages(['mode' => __('validation.in', ['attribute' => 'mode'])]);
        }

        $factory = Factory::findOrFail($order->factory_id);
        $eta = $data['eta'] ?? null;

        return Shipment::create([
            'purchase_order_id' => $order->id,
            'proforma_invoice_id' => $invoice?->id,
            'letter_of_credit_id' => $letter?->id,
            'vendor_id' => $order->vendor_id,
            'shipment_no' => $this->numbers->next('SHIPMENT', $factory),
            'mode' => $mode,
            'bl_awb_no' => $data['bl_awb_no'] ?? null,
            'container_no' => $data['container_no'] ?? null,
            'vessel_or_flight' => $data['vessel_or_flight'] ?? null,
            'shipping_date' => $data['shipping_date'] ?? null,
            // Both set from the same value at creation; only one of them
            // ever moves afterwards.
            'original_eta' => $eta,
            'eta' => $eta,
            'port_of_loading' => $data['port_of_loading'] ?? $invoice?->port_of_loading,
            'port_of_discharge' => $data['port_of_discharge'] ?? $invoice?->port_of_discharge,
            'status' => 'PLANNED',
            'document_status' => 'AWAITED',
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ])->fresh();
    }

    /**
     * Moves the shipment along, and records a revised ETA against the
     * original rather than over it.
     *
     * @param  array<string, mixed>  $data  status?, eta?, actual_arrival_date?, document_status?, bl_awb_no?, container_no?, notes?
     */
    public function updateShipment(Shipment $shipment, array $data, ?string $userId = null): Shipment
    {
        if (isset($data['status'])) {
            $status = (string) $data['status'];

            if (! $shipment->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => __('procurement.invalid_transition', ['from' => $shipment->status, 'to' => $status]),
                ])->status(409);
            }
        }

        if (isset($data['document_status'])
            && ! in_array($data['document_status'], Shipment::DOCUMENT_STATUSES, true)) {
            throw ValidationException::withMessages([
                'document_status' => __('validation.in', ['attribute' => 'document_status']),
            ]);
        }

        return DB::transaction(function () use ($shipment, $data): Shipment {
            $status = $data['status'] ?? $shipment->status;
            $arrival = $data['actual_arrival_date'] ?? $shipment->actual_arrival_date;

            // Landing without a date leaves the delivery record blank, which
            // is the one number this table exists to produce.
            if ($status === 'ARRIVED' && $arrival === null) {
                $arrival = CarbonImmutable::today();
            }

            $shipment->forceFill([
                'status' => $status,
                'document_status' => $data['document_status'] ?? $shipment->document_status,
                'bl_awb_no' => $data['bl_awb_no'] ?? $shipment->bl_awb_no,
                'container_no' => $data['container_no'] ?? $shipment->container_no,
                'vessel_or_flight' => $data['vessel_or_flight'] ?? $shipment->vessel_or_flight,
                'shipping_date' => $data['shipping_date'] ?? $shipment->shipping_date,
                // The revised ETA moves; the original never does.
                'eta' => $data['eta'] ?? $shipment->eta,
                'actual_arrival_date' => $arrival,
                'cleared_date' => $status === 'CLEARED'
                    ? ($data['cleared_date'] ?? CarbonImmutable::today())
                    : $shipment->cleared_date,
                'notes' => isset($data['notes'])
                    ? trim(($shipment->notes ?? '')."\n".$data['notes'])
                    : $shipment->notes,
            ])->save();

            // A shipped consignment moves its letter of credit along with it.
            if ($status === 'SHIPPED' && $shipment->letter_of_credit_id !== null) {
                $letter = LetterOfCredit::find($shipment->letter_of_credit_id);

                if ($letter !== null && $letter->canTransitionTo('SHIPPED')) {
                    $letter->forceFill(['status' => 'SHIPPED'])->save();
                }
            }

            return $shipment->fresh();
        });
    }

    public function cancelShipment(Shipment $shipment, ?string $reason = null): Shipment
    {
        if (! $shipment->canTransitionTo('CANCELLED')) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $shipment->status, 'to' => 'CANCELLED']),
            ])->status(409);
        }

        $shipment->forceFill([
            'status' => 'CANCELLED',
            'cancelled_at' => CarbonImmutable::now(),
            'notes' => $reason === null ? $shipment->notes : trim(($shipment->notes ?? '')."\n".$reason),
        ])->save();

        return $shipment->fresh();
    }

    private function letterFor(?string $letterId, PurchaseOrder $order): ?LetterOfCredit
    {
        if ($letterId === null || $letterId === '') {
            return null;
        }

        $letter = LetterOfCredit::where('purchase_order_id', $order->id)->find($letterId);

        if ($letter === null) {
            throw ValidationException::withMessages(['letter_of_credit_id' => __('procurement.unknown_letter_of_credit')]);
        }

        if (in_array($letter->status, ['CANCELLED', 'CLOSED'], true)) {
            throw ValidationException::withMessages([
                'letter_of_credit_id' => __('procurement.lc_not_shippable', ['status' => $letter->status]),
            ])->status(409);
        }

        return $letter;
    }

    private function invoiceFor(?string $invoiceId, PurchaseOrder $order): ?ProformaInvoice
    {
        if ($invoiceId === null || $invoiceId === '') {
            return null;
        }

        $invoice = ProformaInvoice::where('purchase_order_id', $order->id)->find($invoiceId);

        if ($invoice === null) {
            throw ValidationException::withMessages(['proforma_invoice_id' => __('procurement.unknown_proforma_invoice')]);
        }

        return $invoice;
    }

    private function assertPiTransition(ProformaInvoice $invoice, string $to): void
    {
        if (! $invoice->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $invoice->status, 'to' => $to]),
            ])->status(409);
        }
    }

    private function assertLcTransition(LetterOfCredit $letter, string $to): void
    {
        if (! $letter->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $letter->status, 'to' => $to]),
            ])->status(409);
        }
    }
}
