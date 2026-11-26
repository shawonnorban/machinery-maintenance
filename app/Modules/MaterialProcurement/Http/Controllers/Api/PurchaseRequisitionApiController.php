<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\MaterialProcurement\Models\PurchaseRequisition;
use App\Modules\MaterialProcurement\Models\PurchaseRequisitionLine;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\MaterialProcurement\Models\QuotationRevision;
use App\Modules\MaterialProcurement\Support\Procurement;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Material Procurement → Purchase Requisition (AnnotechERP 08/03): Create
 * Requisition — by confirmed order (its buyer, style, quantity and delivery
 * date loaded and never edited here) or direct — with its fabric and
 * accessories lines picked from the master data; and My Requisitions, each
 * opening to its lines, quotations and where each line stands. A draft is
 * saved, edited, submitted to all suppliers or deleted; a submitted one is
 * never deleted. Addressed by its number (PR-2026-00001).
 */
class PurchaseRequisitionApiController extends ApiController
{
    /** What the form picks from: confirmed orders, the fabric and accessories masters, the units, and who is raising it. */
    public function options(): JsonResponse
    {
        $this->allow('material.requisition.view');
        $company = app(TenantContext::class)->companyId();
        $orders = Order::query()->where('status', 'CONFIRMED')->orderByDesc('order_no')->get();
        $info = self::orderInfo($orders);
        $items = MaterialItem::query()->where('active', true)->orderBy('item_code')->get();

        return ApiResponse::ok([
            'pr_no' => Procurement::previewNumber('PR', 'mp_requisitions', 'pr_no', now()->toDateString()),
            'today' => now()->toDateString(),
            'raiser' => self::raiser($this->caller()->auditUserId()),
            'orders' => $orders->map(fn (Order $o): array => ['value' => $o->id, 'label' => $info[$o->id]['label']] + $info[$o->id])->values()->all(),
            'items' => collect(PurchaseRequisitionLine::KINDS)->mapWithKeys(fn (string $kind): array => [$kind => $items->where('kind', $kind)->map(fn (MaterialItem $i): array => [
                'value' => $i->id, 'label' => self::itemLabel($i), 'category' => $i->category, 'sub_category' => $i->sub_category, 'colour' => $i->colour,
                'gsm' => $i->gsm, 'width' => $i->width, 'size' => $i->size, 'uom' => UomCodes::normalize($i->uom), 'specification' => $i->specification,
            ])->values()->all()])->all(),
            'uoms' => UomCodes::options($company),
        ]);
    }

    /** Every requisition, newest first, with its lines. */
    public function index(): JsonResponse
    {
        $this->allow('material.requisition.view');

        return ApiResponse::ok($this->present(PurchaseRequisition::query()->orderByDesc('pr_no')->get()));
    }

    public function show(string $requisition): JsonResponse
    {
        $this->allow('material.requisition.view');

        return ApiResponse::ok($this->present(collect([$this->find($requisition)]))[0]);
    }

    /** A new requisition, saved as a draft — or submitted at once (Submit requisition). */
    public function store(Request $request): JsonResponse
    {
        $this->allow('material.requisition.manage');
        $data = $this->validated($request);
        $me = $this->caller()->auditUserId();
        $row = DB::transaction(function () use ($data, $me): PurchaseRequisition {
            $today = now()->toDateString();
            $row = PurchaseRequisition::query()->create([
                'pr_no' => Procurement::nextNumber('PR', 'mp_requisitions', 'pr_no', $today), 'req_type' => $data['req_type'], 'req_date' => $today,
                'order_id' => $data['order_id'], 'remarks' => $data['remarks'], 'created_by' => $me,
                'department_id' => $me ? DB::table('users')->where('id', $me)->value('department_id') : null,
                'status' => $data['submit'] ? 'SUBMITTED' : 'DRAFT', 'submitted_at' => $data['submit'] ? now() : null,
            ]);
            $this->saveLines($row, $data['lines']);

            return $row;
        });
        Procurement::audit('CREATE', 'create-requisition', $row->pr_no, Procurement::en($data['submit'] ? 'materialprocurement.audit.pr_created' : 'materialprocurement.audit.pr_saved', ['pr' => $row->pr_no, 'kind' => $this->kindOf($row), 'lines' => $this->linesOf($row)]));
        if ($data['submit']) {
            $this->announce($row);
        }

        return ApiResponse::created($this->present(collect([$row->fresh()]))[0]);
    }

    /** A draft's order, lines and remarks — saved again as a draft, or submitted. */
    public function update(Request $request, string $requisition): JsonResponse
    {
        $this->allow('material.requisition.manage');
        $row = $this->find($requisition);
        $this->assertDraft($row);
        $data = $this->validated($request, $row);
        DB::transaction(function () use ($row, $data): void {
            $row->update(['order_id' => $data['order_id'], 'remarks' => $data['remarks']] + ($data['submit'] ? ['status' => 'SUBMITTED', 'submitted_at' => now()] : []));
            PurchaseRequisitionLine::query()->where('requisition_id', $row->id)->delete();
            $this->saveLines($row, $data['lines']);
        });
        Procurement::audit('UPDATE', 'create-requisition', $row->pr_no, Procurement::en('materialprocurement.audit.pr_updated', ['pr' => $row->pr_no, 'kind' => $this->kindOf($row), 'lines' => $this->linesOf($row)]));
        if ($data['submit']) {
            $this->announce($row);
        }

        return ApiResponse::ok($this->present(collect([$row->fresh()]))[0]);
    }

    /** Submit to all suppliers: every active supplier may now quote on its lines. */
    public function submit(string $requisition): JsonResponse
    {
        $this->allow('material.requisition.manage');
        $row = $this->find($requisition);
        $this->assertDraft($row);
        $row->update(['status' => 'SUBMITTED', 'submitted_at' => now()]);
        $this->announce($row);

        return ApiResponse::ok($this->present(collect([$row->fresh()]))[0]);
    }

    /** The draft and its lines are removed. Submitted requisitions can never be deleted. */
    public function destroy(string $requisition): JsonResponse
    {
        $this->allow('material.requisition.manage');
        $row = $this->find($requisition);
        if (! $row->isDraft()) {
            throw ValidationException::withMessages(['status' => __('materialprocurement.pr.delete_submitted')]);
        }
        $row->delete();
        Procurement::audit('DELETE', 'my-requisitions', $row->pr_no, Procurement::en('materialprocurement.audit.pr_deleted', ['pr' => $row->pr_no]));

        return ApiResponse::ok(['deleted' => $row->pr_no]);
    }

    /** Submitted: every supplier with an active portal login hears its lines are open for quotation. */
    private function announce(PurchaseRequisition $row): void
    {
        Procurement::audit('UPDATE', 'my-requisitions', $row->pr_no, Procurement::en('materialprocurement.audit.pr_submitted', ['pr' => $row->pr_no]));
        app(NotificationDispatcher::class)->sendToMany(SupplierLoginApiController::activeUsers(), eventType: 'MP_REQUIREMENT_AVAILABLE',
            data: ['pr' => $row->pr_no, 'lines' => PurchaseRequisitionLine::query()->where('requisition_id', $row->id)->count()],
            entityType: 'mp_requisition', entityId: $row->id, actionUrl: '/material-procurement/available-requirements');
    }

    private function find(string $requisition): PurchaseRequisition
    {
        return PurchaseRequisition::query()->where('pr_no', $requisition)->firstOrFail();
    }

    private function assertDraft(PurchaseRequisition $row): void
    {
        if (! $row->isDraft()) {
            throw ValidationException::withMessages(['status' => __('materialprocurement.pr.locked')]);
        }
    }

    /**
     * The form, checked as Brickland words it: the order for a By Order
     * requisition, at least one line, and each line's item (of its own tab),
     * quantity, unit and date — named by its line number within its tab.
     *
     * @return array{req_type: string, order_id: ?string, remarks: ?string, submit: bool, lines: list<array<string, mixed>>}
     */
    private function validated(Request $request, ?PurchaseRequisition $row = null): array
    {
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'req_type' => [$row ? 'nullable' : 'required', Rule::in(PurchaseRequisition::TYPES)],
            'order_id' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string', 'max:4000'],
            'submit' => ['nullable', 'boolean'],
            'lines' => ['nullable', 'array'],
            'lines.*.kind' => ['nullable', Rule::in(PurchaseRequisitionLine::KINDS)],
            'lines.*.item_id' => ['nullable', 'string'],
            'lines.*.req_qty' => ['nullable'],
            'lines.*.uom' => ['nullable', 'string', 'max:16'],
            'lines.*.req_date' => ['nullable', 'date'],
            'lines.*.specification' => ['nullable', 'string', 'max:500'],
            'lines.*.remarks' => ['nullable', 'string', 'max:500'],
        ], ['req_type.required' => __('materialprocurement.pr.type_required')]);
        $type = $row?->req_type ?? $data['req_type'];
        $errors = [];

        $orderId = $type === 'BY_ORDER' ? ($data['order_id'] ?? null) : null;
        if ($type === 'BY_ORDER' && (! $orderId || ! Order::query()->whereKey($orderId)->where('status', 'CONFIRMED')->exists())) {
            $errors['order_id'] = __('materialprocurement.pr.order_required');
        }

        $lines = array_values($data['lines'] ?? []);
        if ($lines === []) {
            $errors['lines'] = __('materialprocurement.pr.lines_required');
        }
        $items = MaterialItem::query()->whereIn('id', array_filter(array_column($lines, 'item_id')))->get()->keyBy('id');
        $uoms = UomCodes::codes($company);
        $seen = [];
        $out = [];
        foreach ($lines as $i => $line) {
            $kind = $line['kind'] ?? 'FABRIC';
            $n = $seen[$kind] = ($seen[$kind] ?? 0) + 1;
            $item = $items[$line['item_id'] ?? ''] ?? null;
            $qty = $line['req_qty'] ?? null;
            $uom = UomCodes::normalize($line['uom'] ?? null);
            if (! $item || $item->kind !== $kind) {
                $errors["lines.{$i}.item_id"] = __('materialprocurement.pr.line_item', ['n' => $n]);
            }
            if (! is_numeric($qty) || (float) $qty <= 0) {
                $errors["lines.{$i}.req_qty"] = __('materialprocurement.pr.line_qty', ['n' => $n]);
            }
            if (! $uom) {
                $errors["lines.{$i}.uom"] = __('materialprocurement.pr.line_uom', ['n' => $n]);
            } elseif (! in_array($uom, $uoms, true)) {
                $errors["lines.{$i}.uom"] = __('materialprocurement.pr.line_uom_unknown', ['n' => $n, 'uom' => $uom]);
            }
            if (empty($line['req_date'])) {
                $errors["lines.{$i}.req_date"] = __('materialprocurement.pr.line_date', ['n' => $n]);
            }
            $out[] = ['kind' => $kind, 'item_id' => $item?->id, 'req_qty' => $qty, 'uom' => $uom, 'req_date' => $line['req_date'] ?? null,
                'specification' => $line['specification'] ?? null, 'remarks' => $line['remarks'] ?? null];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return ['req_type' => $type, 'order_id' => $orderId, 'remarks' => $data['remarks'] ?? null, 'submit' => (bool) ($data['submit'] ?? false), 'lines' => $out];
    }

    /** @param list<array<string, mixed>> $lines */
    /** "By Order LPP-2026-000048" or "Direct", as the audit trail names a requisition. */
    private function kindOf(PurchaseRequisition $row): string
    {
        $order = $row->order_id ? Order::query()->whereKey($row->order_id)->value('order_no') : null;

        return $row->req_type === 'BY_ORDER' ? trim('By Order '.$order) : 'Direct';
    }

    /** "1 line(s): Denim 300 Kg", as the audit trail lists a requisition's lines. */
    private function linesOf(PurchaseRequisition $row): string
    {
        $lines = PurchaseRequisitionLine::query()->where('requisition_id', $row->id)->orderBy('sort_order')->get();
        $names = MaterialItem::query()->whereIn('id', $lines->pluck('item_id'))->pluck('name', 'id');

        return Procurement::en('materialprocurement.audit.lines', ['n' => $lines->count(), 'list' => $lines->map(fn (PurchaseRequisitionLine $l): string => trim(($names[$l->item_id] ?? '').' '.Procurement::qty((float) $l->req_qty).' '.$l->uom))->implode(', ')]);
    }

    private function saveLines(PurchaseRequisition $row, array $lines): void
    {
        foreach ($lines as $i => $line) {
            PurchaseRequisitionLine::query()->create(['requisition_id' => $row->id, 'sort_order' => $i + 1] + $line);
        }
    }

    /** Who is raising a requisition, as its header shows them. @return array<string, ?string> */
    private static function raiser(?string $userId): array
    {
        $user = $userId ? DB::table('users')->where('id', $userId)->first(['name', 'employee_code', 'phone', 'department_id']) : null;

        return [
            'name' => $user?->name, 'employee_code' => $user?->employee_code, 'contact' => $user?->phone,
            'department' => $user?->department_id ? DB::table('departments')->where('id', $user->department_id)->value('name') : null,
            // Designation lives with the employee record (HR), which is not built yet.
            'designation' => null,
        ];
    }

    /** An item as the pickers and the lines show it: "FAB-001 — Cotton Jersey · White · 160 GSM". */
    private static function itemLabel(MaterialItem $i): string
    {
        return $i->item_code.' — '.implode(' · ', array_filter([$i->name, $i->colour, $i->gsm ? "{$i->gsm} GSM" : null, $i->kind === 'TRIMS' ? $i->size : null]));
    }

    /**
     * What a confirmed order brings to a requisition: its buyer, style,
     * quantity, delivery date, the merchandiser who raised it and the buyer's
     * contact.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function orderInfo(Collection $orders): array
    {
        $buyers = Buyer::query()->whereIn('id', $orders->pluck('buyer_id')->filter())->get(['id', 'short_name', 'name', 'contact_person'])->keyBy('id');
        $styles = Style::query()->whereIn('id', $orders->pluck('style_id')->filter())->get(['id', 'style_ref', 'style_ext'])->keyBy('id');
        $names = Procurement::names($orders->pluck('created_by')->all());

        return $orders->mapWithKeys(function (Order $o) use ($buyers, $styles, $names): array {
            $buyer = $buyers[$o->buyer_id] ?? null;
            $style = $styles[$o->style_id] ?? null;
            $styleNo = $style ? trim($style->style_ref.($style->style_ext ? "-{$style->style_ext}" : '')) : $o->style_no;
            $buyerName = $buyer?->short_name ?: $buyer?->name;

            return [$o->id => [
                'order_no' => $o->order_no, 'label' => $o->order_no.' — '.implode(' · ', array_filter([$buyerName, $styleNo])),
                'buyer' => $buyerName, 'style_no' => $styleNo, 'order_qty' => $o->total_qty, 'order_unit' => $o->unit,
                'delivery_date' => $o->ship_date?->toDateString(), 'merchandiser' => $names[$o->created_by] ?? null,
                'buyer_contact' => $buyer?->contact_person, 'order_status' => $o->status,
            ]];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    private function present(Collection $rows): array
    {
        $lines = PurchaseRequisitionLine::query()->whereIn('requisition_id', $rows->pluck('id'))->orderBy('sort_order')->get()->groupBy('requisition_id');
        $items = MaterialItem::query()->whereIn('id', $lines->flatten()->pluck('item_id'))->get()->keyBy('id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $lines->flatten()->pluck('approved_supplier_id')->filter())->pluck('name', 'id');
        $approved = QuotationRevision::query()->whereIn('quotation_id', $lines->flatten()->pluck('approved_quotation_id')->filter())
            ->orderBy('revision')->get()->groupBy('quotation_id')->map->last();
        $followups = Procurement::names($lines->flatten()->pluck('followup_user_id')->all());
        $quoted = Quotation::query()->whereIn('requisition_line_id', $lines->flatten()->pluck('id'))->selectRaw('requisition_line_id, count(*) as n')->groupBy('requisition_line_id')->pluck('n', 'requisition_line_id');
        $orders = self::orderInfo(Order::query()->whereIn('id', $rows->pluck('order_id')->filter())->get());
        $names = Procurement::names($rows->pluck('created_by')->all());
        $users = DB::table('users')->whereIn('id', $rows->pluck('created_by')->filter())->get(['id', 'employee_code', 'phone'])->keyBy('id');
        $departments = DB::table('departments')->whereIn('id', $rows->pluck('department_id')->filter())->pluck('name', 'id');

        return $rows->map(function (PurchaseRequisition $r) use ($lines, $items, $suppliers, $quoted, $approved, $followups, $orders, $names, $users, $departments): array {
            $mine = $lines[$r->id] ?? collect();

            return [
                'id' => $r->id, 'pr_no' => $r->pr_no, 'req_type' => $r->req_type, 'req_date' => $r->req_date->toDateString(), 'status' => $r->status,
                'remarks' => $r->remarks, 'submitted_at' => $r->submitted_at?->toIso8601String(), 'editable' => $r->isDraft(),
                'created_by_name' => $names[$r->created_by] ?? null, 'employee_code' => $users[$r->created_by]->employee_code ?? null,
                'contact' => $users[$r->created_by]->phone ?? null, 'department' => $departments[$r->department_id] ?? null, 'designation' => null,
                'order_id' => $r->order_id, 'order' => $r->order_id ? ($orders[$r->order_id] ?? null) : null,
                'lines_count' => $mine->count(),
                'lines' => $mine->map(function (PurchaseRequisitionLine $l) use ($items, $suppliers, $quoted, $approved, $followups): array {
                    $price = $l->approved_quotation_id ? ($approved[$l->approved_quotation_id] ?? null) : null;
                    $item = $items[$l->item_id] ?? null;

                    return [
                        'id' => $l->id, 'kind' => $l->kind, 'item_id' => $l->item_id, 'item_code' => $item?->item_code, 'item_name' => $item?->name,
                        'item_label' => $item ? self::itemLabel($item) : null,
                        'item_detail' => $item ? implode(' · ', array_filter([__('materialprocurement.pr.kind.'.$l->kind), $item->category, $item->colour, $item->gsm ? "{$item->gsm} GSM" : null, $l->kind === 'TRIMS' ? $item->size : null])) : null,
                        'req_qty' => (float) $l->req_qty, 'uom' => $l->uom, 'req_date' => $l->req_date->toDateString(), 'specification' => $l->specification, 'remarks' => $l->remarks,
                        // The suppliers who quoted on it in the Supplier Portal (08/04).
                        'quotations' => (int) ($quoted[$l->id] ?? 0), 'approved_supplier' => $l->approved_supplier_id ? ($suppliers[$l->approved_supplier_id] ?? null) : null, 'status' => $l->status,
                        'approved_price' => $price ? "{$price->currency} ".number_format((float) $price->unit_price, 2) : null, 'followup' => $l->followup_user_id ? ($followups[$l->followup_user_id] ?? null) : null,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }
}
