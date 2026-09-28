<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers\Api;

use App\Modules\Procurement\Actions\ManageImportDocuments;
use App\Modules\Procurement\Models\LetterOfCredit;
use App\Modules\Procurement\Models\LetterOfCreditAmendment;
use App\Modules\Procurement\Models\ProformaInvoice;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Shipment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * PI, LC and shipment tracking (Procurement spec §10).
 *
 * One controller for the three because they are one chain, and the chain is
 * what the screens read: an order's papers, in order.
 */
class ImportDocumentApiController extends ApiController
{
    public const VIEW = 'procurement.import.view_any';

    public const MANAGE = 'procurement.import.manage';

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManageImportDocuments $documents,
    ) {}

    /** Everything on one order's file, in the order it happened. */
    public function forOrder(PurchaseOrder $order): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable((string) $order->factory_id);

        $invoices = ProformaInvoice::where('purchase_order_id', $order->id)
            ->orderBy('version')->get();

        $letters = LetterOfCredit::where('purchase_order_id', $order->id)
            ->with('amendments')->orderBy('lc_date')->get();

        $shipments = Shipment::where('purchase_order_id', $order->id)
            ->orderBy('created_at')->get();

        return ApiResponse::ok([
            'purchase_order' => [
                'id' => $order->id,
                'po_no' => $order->po_no,
                'subtotal' => (string) $order->subtotal,
                'currency' => $order->currency,
            ],
            'proforma_invoices' => $invoices->map(fn (ProformaInvoice $pi): array => $this->invoice($pi))->all(),
            'letters_of_credit' => $letters->map(fn (LetterOfCredit $lc): array => $this->letter($lc))->all(),
            'shipments' => $shipments->map(fn (Shipment $s): array => $this->shipment($s))->all(),
            'can_manage' => $this->caller()->can(self::MANAGE),
        ]);
    }

    /** What is still at sea, and what is late. */
    public function shipments(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = Shipment::query()
            ->with(['purchaseOrder:id,po_no,factory_id', 'vendor:id,name,code', 'letterOfCredit:id,lc_no'])
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('factory_id', $this->context->accessibleFactoryIds()));

        if ($request->boolean('in_flight')) {
            $query->whereIn('status', ['PLANNED', 'SHIPPED', 'IN_TRANSIT']);
        }

        if (is_string($search = $request->query('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(shipment_no) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(bl_awb_no, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(container_no, \'\')) LIKE ?', [$needle]));
        }

        $query = $this->applyFilters($query, $request, ['status', 'mode', 'vendor_id', 'document_status']);
        // Soonest ETA first: the board answers "what lands this week".
        $query = $this->applySort($query, $request, ['shipment_no', 'eta', 'shipping_date', 'status'], 'eta', 'asc');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (Shipment $shipment): array => $this->shipment($shipment),
        );
    }

    public function storeProformaInvoice(Request $request, PurchaseOrder $order): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable((string) $order->factory_id);

        $data = $request->validate([
            'pi_no' => ['required', 'string', 'max:64'],
            'pi_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'incoterm' => ['nullable', 'string', 'max:16'],
            'port_of_loading' => ['nullable', 'string', 'max:120'],
            'port_of_discharge' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::created($this->invoice($this->documents->createProformaInvoice(
            $order, $data, $this->caller()->auditUserId(),
        )));
    }

    public function confirmProformaInvoice(ProformaInvoice $invoice): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachableInvoice($invoice);

        return ApiResponse::ok($this->invoice($this->documents->confirmProformaInvoice($invoice)));
    }

    public function reviseProformaInvoice(Request $request, ProformaInvoice $invoice): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachableInvoice($invoice);

        $data = $request->validate([
            'pi_no' => ['nullable', 'string', 'max:64'],
            'pi_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'incoterm' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::created($this->invoice($this->documents->reviseProformaInvoice(
            $invoice, $data, $this->caller()->auditUserId(),
        )));
    }

    public function storeLetterOfCredit(Request $request, PurchaseOrder $order): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable((string) $order->factory_id);

        $data = $request->validate([
            'lc_no' => ['required', 'string', 'max:64'],
            'bank_name' => ['required', 'string', 'max:160'],
            'branch' => ['nullable', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'lc_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'last_shipment_date' => ['nullable', 'date'],
            'tenor_days' => ['nullable', 'integer', 'min:0'],
            'proforma_invoice_id' => ['nullable', 'string', 'size:26'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $invoice = isset($data['proforma_invoice_id'])
            ? ProformaInvoice::where('purchase_order_id', $order->id)->findOrFail($data['proforma_invoice_id'])
            : null;

        return ApiResponse::created($this->letter($this->documents->openLetterOfCredit(
            $order, $invoice, $data, $this->caller()->auditUserId(),
        )));
    }

    public function markLetterOpened(LetterOfCredit $letter): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachableLetter($letter);

        return ApiResponse::ok($this->letter($this->documents->markLetterOpened($letter)));
    }

    public function amendLetterOfCredit(Request $request, LetterOfCredit $letter): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachableLetter($letter);

        $data = $request->validate([
            'new_amount' => ['nullable', 'numeric', 'gt:0'],
            'new_expiry_date' => ['nullable', 'date'],
            'amendment_date' => ['nullable', 'date'],
            'bank_charge' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return ApiResponse::ok($this->letter($this->documents->amendLetterOfCredit(
            $letter, $data, $this->caller()->auditUserId(),
        )));
    }

    public function closeLetterOfCredit(LetterOfCredit $letter): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachableLetter($letter);

        return ApiResponse::ok($this->letter($this->documents->closeLetterOfCredit($letter)));
    }

    public function storeShipment(Request $request, PurchaseOrder $order): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable((string) $order->factory_id);

        $data = $request->validate([
            'mode' => ['nullable', Rule::in(Shipment::MODES)],
            'bl_awb_no' => ['nullable', 'string', 'max:64'],
            'container_no' => ['nullable', 'string', 'max:64'],
            'vessel_or_flight' => ['nullable', 'string', 'max:120'],
            'shipping_date' => ['nullable', 'date'],
            'eta' => ['nullable', 'date'],
            'port_of_loading' => ['nullable', 'string', 'max:120'],
            'port_of_discharge' => ['nullable', 'string', 'max:120'],
            'letter_of_credit_id' => ['nullable', 'string', 'size:26'],
            'proforma_invoice_id' => ['nullable', 'string', 'size:26'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::created($this->shipment($this->documents->createShipment(
            $order, $data, $this->caller()->auditUserId(),
        )));
    }

    public function updateShipment(Request $request, Shipment $shipment): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachableShipment($shipment);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(Shipment::STATUSES)],
            'document_status' => ['nullable', Rule::in(Shipment::DOCUMENT_STATUSES)],
            'bl_awb_no' => ['nullable', 'string', 'max:64'],
            'container_no' => ['nullable', 'string', 'max:64'],
            'vessel_or_flight' => ['nullable', 'string', 'max:120'],
            'shipping_date' => ['nullable', 'date'],
            'eta' => ['nullable', 'date'],
            'actual_arrival_date' => ['nullable', 'date'],
            'cleared_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::ok($this->shipment($this->documents->updateShipment(
            $shipment, $data, $this->caller()->auditUserId(),
        )));
    }

    public function cancelShipment(Request $request, Shipment $shipment): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachableShipment($shipment);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->shipment($this->documents->cancelShipment($shipment, $data['reason'] ?? null)));
    }

    /**
     * @return array<string, mixed>
     */
    private function invoice(ProformaInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'pi_no' => $invoice->pi_no,
            'pi_date' => $invoice->pi_date?->toDateString(),
            'version' => (int) $invoice->version,
            'supersedes_id' => $invoice->supersedes_id,
            'amount' => (string) $invoice->amount,
            'currency' => $invoice->currency,
            'status' => $invoice->status,
            'incoterm' => $invoice->incoterm,
            'port_of_loading' => $invoice->port_of_loading,
            'port_of_discharge' => $invoice->port_of_discharge,
            'notes' => $invoice->notes,
            'confirmed_at' => $invoice->confirmed_at?->toIso8601String(),
            'superseded_at' => $invoice->superseded_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function letter(LetterOfCredit $letter): array
    {
        $letter->loadMissing(['amendments', 'purchaseOrder:id,po_no,subtotal,currency']);

        return [
            'id' => $letter->id,
            'lc_no' => $letter->lc_no,
            'lc_date' => $letter->lc_date?->toDateString(),
            'bank_name' => $letter->bank_name,
            'branch' => $letter->branch,
            'amount' => (string) $letter->amount,
            'currency' => $letter->currency,
            'expiry_date' => $letter->expiry_date?->toDateString(),
            'days_to_expiry' => $letter->daysToExpiry(),
            'last_shipment_date' => $letter->last_shipment_date?->toDateString(),
            'tenor_days' => $letter->tenor_days,
            'status' => $letter->status,
            'proforma_invoice_id' => $letter->proforma_invoice_id,
            // §10's "LC vs PO amount matching", as a number rather than a
            // thing the merchandiser works out on paper.
            'order_subtotal' => (string) ($letter->purchaseOrder?->subtotal ?? '0'),
            'variance_against_order' => $letter->varianceAgainstOrder(),
            'notes' => $letter->notes,
            'amendments' => $letter->amendments->map(fn (LetterOfCreditAmendment $row): array => [
                'id' => $row->id,
                'amendment_no' => (int) $row->amendment_no,
                'amendment_date' => $row->amendment_date?->toDateString(),
                'previous_amount' => (string) $row->previous_amount,
                'new_amount' => (string) $row->new_amount,
                'amount_change' => $row->amountChange(),
                'previous_expiry_date' => $row->previous_expiry_date?->toDateString(),
                'new_expiry_date' => $row->new_expiry_date?->toDateString(),
                'bank_charge' => (string) $row->bank_charge,
                'reason' => $row->reason,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shipment(Shipment $shipment): array
    {
        $shipment->loadMissing(['vendor:id,name,code', 'letterOfCredit:id,lc_no', 'purchaseOrder:id,po_no']);

        return [
            'id' => $shipment->id,
            'shipment_no' => $shipment->shipment_no,
            'mode' => $shipment->mode,
            'bl_awb_no' => $shipment->bl_awb_no,
            'container_no' => $shipment->container_no,
            'vessel_or_flight' => $shipment->vessel_or_flight,
            'shipping_date' => $shipment->shipping_date?->toDateString(),
            'original_eta' => $shipment->original_eta?->toDateString(),
            'eta' => $shipment->eta?->toDateString(),
            'days_to_eta' => $shipment->daysToEta(),
            'overdue' => $shipment->isOverdue(),
            'actual_arrival_date' => $shipment->actual_arrival_date?->toDateString(),
            'delay_days' => $shipment->delayDays(),
            'cleared_date' => $shipment->cleared_date?->toDateString(),
            'port_of_loading' => $shipment->port_of_loading,
            'port_of_discharge' => $shipment->port_of_discharge,
            'status' => $shipment->status,
            'document_status' => $shipment->document_status,
            'purchase_order' => $shipment->purchaseOrder === null ? null : ['id' => $shipment->purchaseOrder->id, 'po_no' => $shipment->purchaseOrder->po_no],
            'vendor' => $shipment->vendor === null ? null : ['id' => $shipment->vendor->id, 'name' => $shipment->vendor->name],
            'letter_of_credit' => $shipment->letterOfCredit === null ? null : ['id' => $shipment->letterOfCredit->id, 'lc_no' => $shipment->letterOfCredit->lc_no],
            'notes' => $shipment->notes,
        ];
    }

    private function assertReachable(string $factoryId): void
    {
        if (! $this->context->canAccessFactory($factoryId)) {
            abort(404);
        }
    }

    private function assertReachableInvoice(ProformaInvoice $invoice): void
    {
        $order = PurchaseOrder::findOrFail($invoice->purchase_order_id);
        $this->assertReachable((string) $order->factory_id);
    }

    private function assertReachableLetter(LetterOfCredit $letter): void
    {
        $order = PurchaseOrder::findOrFail($letter->purchase_order_id);
        $this->assertReachable((string) $order->factory_id);
    }

    private function assertReachableShipment(Shipment $shipment): void
    {
        $order = PurchaseOrder::findOrFail($shipment->purchase_order_id);
        $this->assertReachable((string) $order->factory_id);
    }
}
