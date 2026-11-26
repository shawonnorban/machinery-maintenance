<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\YarnRequisition;
use App\Modules\Knitting\Models\YarnRequisitionLine;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Yarn Procurement (AnnotechERP 15/02) → Yarn Purchase
 * Indent and Yarn Indent Approval List, as Brickland works them: the yarn an
 * order needs, line by line, local and import apart, the header and lines
 * saved together and numbered YR-2026100001. A draft (or one the approver
 * sent back) is rewritten; Submit from the register sends it for approval;
 * the approve list approves it (orderable) or rejects it with a reason (back
 * to the desk). An approved indent is closed when nothing more is to be
 * bought on it. A requisition travels by its number.
 */
class YarnRequisitionApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('knitting.yarn.view');

        return ApiResponse::ok(YarnProcurement::options() + [
            'requisitions' => YarnRequisition::query()->orderByDesc('requisition_no')->limit(300)->get(['requisition_no', 'status'])
                ->map(fn ($r) => ['value' => $r->requisition_no, 'label' => $r->requisition_no])->values()->all(),
            'statuses' => YarnRequisition::STATUSES,
        ]);
    }

    /** The register: nothing filled in lists the most recent requisitions. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('knitting.yarn.view');

        return ApiResponse::ok($this->rows($this->search($request)->limit(200)->get()));
    }

    public function show(string $requisition): JsonResponse
    {
        $this->allow('knitting.yarn.view');

        return ApiResponse::ok($this->present($this->find($requisition)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('knitting.yarn.manage');
        $data = $this->validated($request);
        $row = DB::transaction(function () use ($data): YarnRequisition {
            $row = YarnRequisition::query()->create($this->header($data) + [
                'requisition_no' => YarnProcurement::nextNumber('YR', 'knit_yarn_requisitions', 'requisition_no', $data['requisition_date']),
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
            ]);
            $this->writeLines($row, $data['lines']);

            return $row;
        });

        return ApiResponse::created($this->present($row));
    }

    public function update(Request $request, string $requisition): JsonResponse
    {
        $this->allow('knitting.yarn.manage');
        $row = $this->find($requisition);
        $this->assertEditable($row);
        $data = $this->validated($request);
        DB::transaction(function () use ($row, $data): void {
            $row->update($this->header($data));
            $this->writeLines($row, $data['lines']);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    public function destroy(string $requisition): JsonResponse
    {
        $this->allow('knitting.yarn.manage');
        $row = $this->find($requisition);
        $this->assertEditable($row);
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /** From the register: Submit (for approval), Cancel (a draft or rejected one) or Close (an approved or ordered one). */
    public function transition(string $requisition, string $action): JsonResponse
    {
        $this->allow('knitting.yarn.manage');
        $row = $this->find($requisition);
        [$from, $to] = match ($action) {
            'submit' => [['DRAFT', 'REJECTED'], 'WAITING_APPROVAL'],
            'cancel' => [['DRAFT', 'REJECTED'], 'CANCELLED'],
            'close' => [['APPROVED', 'ORDERED'], 'CLOSED'],
            default => abort(404),
        };
        $this->assertFrom($row, $from, $action);
        $row->update(['status' => $to] + ($action === 'submit' ? ['submitted_at' => now(), 'decision_note' => null, 'decided_at' => null, 'decided_by' => null] : []));

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** Yarn Indent Approval List: only requisitions waiting on a decision, the earliest wanted first. */
    public function approvals(Request $request): JsonResponse
    {
        $this->allow('knitting.yarn.approve');

        return ApiResponse::ok($this->rows($this->search($request, false)->where('status', 'WAITING_APPROVAL')->reorder()->orderByRaw('required_date asc nulls last')->orderBy('requisition_no')->get()));
    }

    /** Approve (orderable) or reject (back to the desk) the requisitions ticked; a rejection carries its reason. */
    public function decide(Request $request): JsonResponse
    {
        $this->allow('knitting.yarn.approve');
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'numbers' => ['required', 'array', 'min:1'], 'numbers.*' => ['string'],
            'note' => [$request->input('action') === 'reject' ? 'required' : 'nullable', 'string', 'max:255'],
        ], ['note.required' => __('knitting.yarn.v.reject_note')]);
        $rows = YarnRequisition::query()->whereIn('requisition_no', $data['numbers'])->get();
        foreach ($rows as $row) {
            $this->assertFrom($row, ['WAITING_APPROVAL'], $data['action']);
        }
        DB::transaction(fn () => $rows->each->update([
            'status' => $data['action'] === 'approve' ? 'APPROVED' : 'REJECTED',
            'decision_note' => $data['note'] ?? null, 'decided_at' => now(), 'decided_by' => $this->caller()->auditUserId(),
        ]));

        return ApiResponse::ok(['count' => $rows->count()]);
    }

    /* ---------------- helpers ---------------- */

    private function search(Request $request, bool $withStatus = true): Builder
    {
        $f = $request->validate([
            'no' => ['nullable', 'string', 'max:24'], 'buyer' => ['nullable', 'string'], 'status' => ['nullable', Rule::in(YarnRequisition::STATUSES)],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);

        return YarnRequisition::query()
            ->when(! empty($f['no']), fn ($q) => $q->where('requisition_no', 'ilike', '%'.addcslashes($f['no'], '%_\\').'%'))
            ->when(! empty($f['buyer']), fn ($q) => $q->where('buyer_id', $f['buyer']))
            ->when($withStatus && ! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('requisition_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('requisition_date', '<=', $f['to']))
            ->orderByDesc('requisition_date')->orderByDesc('requisition_no');
    }

    /** @param Collection<int, YarnRequisition> $rows */
    private function rows($rows): array
    {
        $stores = Store::query()->whereIn('id', $rows->pluck('store_id'))->pluck('name', 'id');
        $buyers = Buyer::query()->whereIn('id', $rows->pluck('buyer_id')->filter())->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id')->filter())->pluck('order_no', 'id');
        $lines = YarnRequisitionLine::query()->whereIn('requisition_id', $rows->pluck('id'))->get()->groupBy('requisition_id');
        $users = DB::table('users')->whereIn('id', $rows->pluck('created_by')->filter())->pluck('name', 'id');

        return $rows->map(fn (YarnRequisition $r) => [
            'requisition_no' => $r->requisition_no, 'requisition_date' => $r->requisition_date->toDateString(), 'store' => $stores[$r->store_id] ?? null,
            'buyer' => $buyers[$r->buyer_id] ?? null, 'order_no' => $orders[$r->order_id] ?? null, 'required_date' => $r->required_date?->toDateString(),
            'lines' => ($lines[$r->id] ?? collect())->count(),
            'qty_local' => round((float) ($lines[$r->id] ?? collect())->sum('qty_local'), 4), 'qty_import' => round((float) ($lines[$r->id] ?? collect())->sum('qty_import'), 4),
            'total' => round((float) ($lines[$r->id] ?? collect())->sum(fn ($l) => (float) $l->qty_local + (float) $l->qty_import), 4),
            'status' => $r->status, 'editable' => $r->isEditable(), 'decision_note' => $r->decision_note, 'raised_by' => $users[$r->created_by] ?? null,
        ])->values()->all();
    }

    private function find(string $requisition): YarnRequisition
    {
        return YarnRequisition::query()->where('requisition_no', $requisition)->firstOrFail();
    }

    private function assertEditable(YarnRequisition $row): void
    {
        if (! $row->isEditable()) {
            throw ValidationException::withMessages(['status' => __('knitting.yarn.v.locked', ['status' => __("knitting.yarn.status.{$row->status}")])]);
        }
    }

    private function assertFrom(YarnRequisition $row, array $from, string $action): void
    {
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.yarn.v.cannot', ['no' => $row->requisition_no, 'action' => __("knitting.yarn.action.{$action}"), 'status' => __("knitting.yarn.status.{$row->status}")])]);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $company = app(TenantContext::class)->companyId();
        $qty = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
        $data = $request->validate([
            'requisition_date' => ['required', 'date'],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'required_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            ...YarnProcurement::lineRules('lines.*'),
            'lines.*.qty_local' => $qty, 'lines.*.qty_import' => $qty, 'lines.*.booking_price' => $qty,
        ], [
            'requisition_date.required' => __('knitting.yarn.v.date'), 'store_id.required' => __('knitting.yarn.v.store'),
            'lines.required' => __('knitting.yarn.v.no_lines'), 'lines.min' => __('knitting.yarn.v.no_lines'),
        ]);
        $errors = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $n = $i + 1;
            if (empty($l['composition_id']) && empty($l['yarn_count_id'])) {
                $errors["lines.{$i}.composition_id"] = __('knitting.yarn.v.line_yarn', ['n' => $n]);
            }
            if (empty($l['uom'])) {
                $errors["lines.{$i}.uom"] = __('knitting.yarn.v.line_uom', ['n' => $n]);
            }
            if ((float) ($l['qty_local'] ?? 0) + (float) ($l['qty_import'] ?? 0) <= 0) {
                $errors["lines.{$i}.qty_local"] = __('knitting.yarn.v.line_qty', ['n' => $n]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $data['lines'] = array_values($data['lines']);

        return $data;
    }

    /** @return array<string, mixed> */
    private function header(array $data): array
    {
        return collect($data)->only(['requisition_date', 'store_id', 'buyer_id', 'order_id', 'required_date', 'remarks'])->all();
    }

    private function writeLines(YarnRequisition $row, array $lines): void
    {
        YarnRequisitionLine::query()->where('requisition_id', $row->id)->delete();
        foreach ($lines as $i => $l) {
            YarnRequisitionLine::query()->create(collect($l)->only(YarnProcurement::YARN)->all() + [
                'company_id' => $row->company_id, 'requisition_id' => $row->id, 'sort_order' => $i + 1, 'uom' => UomCodes::normalize($l['uom']),
                'qty_local' => (float) ($l['qty_local'] ?? 0), 'qty_import' => (float) ($l['qty_import'] ?? 0), 'booking_price' => $l['booking_price'] ?? null,
            ]);
        }
    }

    /** @return array<string, mixed> */
    public static function presentRequisition(YarnRequisition $row): array
    {
        $names = YarnProcurement::names();
        $lines = YarnRequisitionLine::query()->where('requisition_id', $row->id)->orderBy('sort_order')->get();

        return [
            'requisition_no' => $row->requisition_no, 'requisition_date' => $row->requisition_date->toDateString(), 'store_id' => $row->store_id,
            'buyer_id' => $row->buyer_id, 'order_id' => $row->order_id, 'required_date' => $row->required_date?->toDateString(), 'remarks' => $row->remarks,
            'status' => $row->status, 'editable' => $row->isEditable(), 'decision_note' => $row->decision_note,
            'lines' => $lines->map(fn (YarnRequisitionLine $l) => [
                'composition_id' => $l->composition_id, 'composition' => $names['compositions'][$l->composition_id] ?? null,
                'yarn_count_id' => $l->yarn_count_id, 'count' => $names['counts'][$l->yarn_count_id] ?? null,
                'colour' => $l->colour, 'brand' => $l->brand, 'uom' => $l->uom, 'qty_local' => (float) $l->qty_local, 'qty_import' => (float) $l->qty_import,
                'total' => round((float) $l->qty_local + (float) $l->qty_import, 4), 'knit_start_date' => $l->knit_start_date?->toDateString(),
                'ship_date' => $l->ship_date?->toDateString(), 'booking_price' => $l->booking_price !== null ? (float) $l->booking_price : null, 'remarks' => $l->remarks,
            ])->values()->all(),
        ];
    }

    private function present(YarnRequisition $row): array
    {
        return self::presentRequisition($row);
    }
}
