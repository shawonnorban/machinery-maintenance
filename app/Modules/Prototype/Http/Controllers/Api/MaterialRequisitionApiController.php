<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\Merchandise\Models\AccReceipt;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\SampleProgram;
use App\Modules\Prototype\Models\MaterialRequisition;
use App\Modules\Prototype\Models\MaterialRequisitionLine;
use App\Modules\Prototype\Models\MaterialRequisitionProgram;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Support\Prototype;
use App\Modules\Prototype\Support\SampleCards;
use App\Modules\Prototype\Support\SampleLedger;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Prototype Desk → Prototype Material Requisition (AnnotechERP 07/03): the
 * fabric, trims, accessories and packaging a sample asks of the stores
 * (MR-2026-00001). The header, the sample programs it covers and the material
 * lines save together — the lines replaced with exactly the grid, at least
 * one. Submit, Dept. Approve, Store Verify (the store has entered Available
 * Qty and Warehouse on every line; the lines lock) and Approve walk it to
 * issue; Reject needs the reason saved first and a rejected one saved again
 * is a draft; Cancel holds until anything is issued; Delete until approval.
 */
class MaterialRequisitionApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('prototype.material.view');
        $company = app(TenantContext::class)->companyId();
        $programs = SampleProgram::query()->orderByDesc('program_date')->orderByDesc('program_no')->get(['id', 'program_no', 'program_date']);
        $raised = MaterialRequisitionProgram::query()->whereIn('program_id', $programs->pluck('id'))->get(['program_id', 'requisition_id']);
        $mrs = MaterialRequisition::query()->whereIn('id', $raised->pluck('requisition_id'))->get(['id', 'mr_no', 'req_date', 'state'])->keyBy('id');
        $companyName = Company::query()->whereKey($company)->value('name');

        return ApiResponse::ok([
            'samples' => SampleCards::options(Sample::query()->where('state', '!=', 'CANCELLED')->orderByDesc('sample_no')->get()),
            'users' => Prototype::users(), 'states' => MaterialRequisition::STATES, 'material_groups' => MaterialRequisition::MATERIAL_GROUPS,
            'requisition_types' => AccReceipt::REQUISITION_TYPES,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name'])->map(fn ($d): array => ['value' => $d->id, 'label' => $d->name])->all(),
            'stores' => Store::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->all(),
            'items' => MaterialItem::query()->where('active', true)->orderBy('name')->get(['id', 'item_code', 'name', 'uom', 'kind'])
                ->map(fn ($i): array => ['value' => $i->id, 'label' => "{$i->name} ({$i->item_code})", 'uom' => $i->uom, 'kind' => $i->kind])->all(),
            'fabric_types' => FabricType::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($f): array => ['value' => $f->id, 'label' => "{$f->name} ({$f->code})"])->all(),
            // Sample Programs: every program the section can requisition against, and what is already raised on each.
            'programs' => $programs->map(fn ($p): array => [
                'value' => $p->id, 'label' => $p->program_no, 'program_no' => $p->program_no, 'company' => $companyName, 'program_date' => $p->program_date?->toDateString(),
                'requisitions' => $raised->where('program_id', $p->id)->map(fn ($r) => $mrs[$r->requisition_id] ?? null)->filter()
                    ->map(fn ($m): array => ['mr_no' => $m->mr_no, 'req_date' => $m->req_date->toDateString(), 'state' => $m->state])->sortByDesc('mr_no')->values()->all(),
            ])->all(),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->allow('prototype.material.view');

        return ApiResponse::ok($this->present(MaterialRequisition::query()->orderByDesc('req_date')->orderByDesc('mr_no')->get()));
    }

    public function show(string $mr): JsonResponse
    {
        $this->allow('prototype.material.view');

        return ApiResponse::ok($this->present(collect([$this->find($mr)]), true)[0]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('prototype.material.manage');
        $data = $this->validated($request, null);
        $row = DB::transaction(function () use ($data): MaterialRequisition {
            $row = MaterialRequisition::query()->create([
                ...collect($data)->except(['programs', 'lines'])->all(), 'state' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
                'mr_no' => Prototype::nextNumber('MR', 'proto_material_requisitions', 'mr_no', $data['req_date']),
            ]);
            $this->writePrograms($row, $data['programs']);
            $this->writeLines($row, $data['lines']);
            SampleLedger::log($row->sample_id, 'mr_raised', ['mr' => $row->mr_no, 'lines' => count($data['lines'])], $row->mr_no);

            return $row;
        });

        return ApiResponse::created($this->present(collect([$row->fresh()]), true)[0]);
    }

    public function update(Request $request, string $mr): JsonResponse
    {
        $this->allow('prototype.material.manage');
        $row = $this->find($mr);
        if (! $row->isEditable()) {
            throw ValidationException::withMessages(['state' => __('prototype.material.locked', ['state' => __('prototype.mr_state.'.$row->state)])]);
        }
        $data = $this->validated($request, $row);
        DB::transaction(function () use ($row, $data): void {
            $header = collect($data)->except(['programs', 'lines', 'sample_id'])->all();
            // A rejected requisition saved again goes back to draft, its approvals cleared.
            if ($row->state === 'REJECTED') {
                $header += ['state' => 'DRAFT', 'dept_approved_by' => null, 'dept_approved_at' => null, 'store_verified_by' => null, 'store_verified_at' => null, 'rejection_reason' => null];
            }
            $row->update($header);
            $this->writePrograms($row, $data['programs']);
            if ($row->linesOpen()) {
                $this->writeLines($row, $data['lines']);
            }
        });

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    public function destroy(string $mr): JsonResponse
    {
        $this->allow('prototype.material.manage');
        $row = $this->find($mr);
        if (! $row->isEditable() && $row->state !== 'CANCELLED') {
            throw ValidationException::withMessages(['state' => __('prototype.material.delete_locked', ['state' => __('prototype.mr_state.'.$row->state)])]);
        }
        $row->delete();

        return ApiResponse::ok(['removed' => $mr]);
    }

    public function submit(string $mr): JsonResponse
    {
        $this->allow('prototype.material.manage');

        return $this->step($mr, 'DRAFT', 'SUBMITTED', []);
    }

    public function deptApprove(string $mr): JsonResponse
    {
        $this->allow('prototype.material.approve');

        return $this->step($mr, 'SUBMITTED', 'DEPT_APPROVED', ['dept_approved_by' => $this->caller()->auditUserId(), 'dept_approved_at' => now()]);
    }

    /** The store has entered what it holds, and where, on every line; verifying locks the lines. */
    public function verify(string $mr): JsonResponse
    {
        $this->allow('prototype.material.approve');
        $row = $this->find($mr);
        $open = MaterialRequisitionLine::query()->where('requisition_id', $row->id)->where(fn ($q) => $q->whereNull('available_qty')->orWhereNull('warehouse_id'))->exists();
        if ($row->state === 'DEPT_APPROVED' && $open) {
            throw ValidationException::withMessages(['lines' => __('prototype.material.verify_needs')]);
        }

        return $this->step($mr, 'DEPT_APPROVED', 'STORE_VERIFIED', ['store_verified_by' => $this->caller()->auditUserId(), 'store_verified_at' => now()]);
    }

    public function approve(string $mr): JsonResponse
    {
        $this->allow('prototype.material.approve');

        return $this->step($mr, 'STORE_VERIFIED', 'APPROVED', ['approved_by' => $this->caller()->auditUserId(), 'approved_at' => now()]);
    }

    /** Reject is refused until the reason is saved. */
    public function reject(string $mr): JsonResponse
    {
        $this->allow('prototype.material.approve');
        $row = $this->find($mr);
        if (! in_array($row->state, MaterialRequisition::AWAITING, true)) {
            throw ValidationException::withMessages(['state' => __('prototype.material.not_awaiting')]);
        }
        if (blank($row->rejection_reason)) {
            throw ValidationException::withMessages(['rejection_reason' => __('prototype.material.reason_first')]);
        }
        $row->update(['state' => 'REJECTED']);
        SampleLedger::log($row->sample_id, 'mr_rejected', ['mr' => $row->mr_no, 'reason' => $row->rejection_reason], $row->mr_no);
        SampleLedger::resolve(['MATERIAL_REQUIRED', 'MATERIAL_SHORTAGE'], $row->mr_no);

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    public function cancel(string $mr): JsonResponse
    {
        $this->allow('prototype.material.manage');
        $row = $this->find($mr);
        if (in_array($row->state, ['PARTIALLY_ISSUED', 'FULLY_ISSUED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['state' => __('prototype.material.cancel_locked', ['state' => __('prototype.mr_state.'.$row->state)])]);
        }
        $row->update(['state' => 'CANCELLED']);
        SampleLedger::log($row->sample_id, 'mr_cancelled', ['mr' => $row->mr_no], $row->mr_no);
        SampleLedger::resolve(['MATERIAL_REQUIRED', 'MATERIAL_SHORTAGE'], $row->mr_no);

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    private function step(string $mr, string $from, string $to, array $stamp): JsonResponse
    {
        $row = $this->find($mr);
        if ($row->state !== $from) {
            throw ValidationException::withMessages(['state' => __('prototype.material.wrong_state', ['action' => __('prototype.mr_action.'.$to), 'state' => __('prototype.mr_state.'.$row->state)])]);
        }
        $row->update(['state' => $to, ...$stamp]);
        SampleLedger::log($row->sample_id, 'mr_'.strtolower($to), ['mr' => $row->mr_no], $row->mr_no);
        $sampleNo = Sample::query()->whereKey($row->sample_id)->value('sample_no');
        if ($to === 'SUBMITTED') {
            $lines = MaterialRequisitionLine::query()->where('requisition_id', $row->id)->count();
            SampleLedger::raise('MATERIAL_REQUIRED', 'INFO', $row->sample_id, 'STORE', $row->mr_no, ['sample' => $sampleNo, 'mr' => $row->mr_no, 'lines' => $lines]);
        }
        $short = MaterialRequisitionLine::query()->where('requisition_id', $row->id)->whereNotNull('available_qty')->whereColumn('available_qty', '<', 'req_qty')->count();
        if ($to === 'STORE_VERIFIED' && $short > 0) {
            SampleLedger::raise('MATERIAL_SHORTAGE', 'WARNING', $row->sample_id, 'STORE', $row->mr_no, ['sample' => $sampleNo, 'mr' => $row->mr_no, 'lines' => $short]);
        }

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    private function writePrograms(MaterialRequisition $row, array $programs): void
    {
        MaterialRequisitionProgram::query()->where('requisition_id', $row->id)->delete();
        foreach (array_values(array_unique($programs)) as $i => $program) {
            MaterialRequisitionProgram::query()->create(['requisition_id' => $row->id, 'program_id' => $program, 'sort_order' => $i + 1]);
        }
    }

    /** Saving replaces the lines with exactly the grid; the unit is the item's. */
    private function writeLines(MaterialRequisition $row, array $lines): void
    {
        MaterialRequisitionLine::query()->where('requisition_id', $row->id)->delete();
        $uoms = MaterialItem::query()->whereIn('id', collect($lines)->pluck('item_id'))->pluck('uom', 'id');
        foreach (array_values($lines) as $i => $line) {
            MaterialRequisitionLine::query()->create([...$line, 'requisition_id' => $row->id, 'unit' => $uoms[$line['item_id']] ?? null, 'issued_qty' => 0, 'sort_order' => $i + 1]);
        }
    }

    private function find(string $mr): MaterialRequisition
    {
        return MaterialRequisition::query()->where('mr_no', $mr)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?MaterialRequisition $row): array
    {
        $company = app(TenantContext::class)->companyId();
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $qty = ['numeric', 'min:0', 'max:99999999'];
        $data = $request->validate([
            'sample_id' => [$row ? 'nullable' : 'required', $in('proto_samples')],
            'req_date' => ['required', 'date'],
            'required_date' => ['nullable', 'date'],
            'department_id' => ['nullable', $in('departments')],
            'requested_by' => ['required', Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'requisition_type' => ['nullable', Rule::in(AccReceipt::REQUISITION_TYPES)],
            'requisition_to_id' => ['required', $in('stores')],
            'delivery_to_id' => ['required', $in('stores')],
            'remarks' => ['nullable', 'string', 'max:4000'],
            'rejection_reason' => ['nullable', 'string', 'max:4000'],
            'programs' => ['nullable', 'array'],
            'programs.*' => [$in('merch_sample_programs')],
            'lines' => ['nullable', 'array'],
            'lines.*.item_id' => ['required', $in('mp_items')],
            'lines.*.material_group' => ['nullable', Rule::in(MaterialRequisition::MATERIAL_GROUPS)],
            'lines.*.fabric_type_id' => ['nullable', $in('fabric_types')],
            'lines.*.color' => ['nullable', 'string', 'max:96'],
            'lines.*.size' => ['nullable', 'string', 'max:32'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.specification' => ['nullable', 'string', 'max:255'],
            'lines.*.req_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.available_qty' => ['nullable', ...$qty],
            'lines.*.warehouse_id' => ['nullable', $in('stores')],
            'lines.*.remarks' => ['nullable', 'string', 'max:500'],
        ], [
            'sample_id.required' => __('prototype.material.sample_required'), 'req_date.required' => __('prototype.material.date_required'),
            'requested_by.required' => __('prototype.material.requested_by_required'), 'requisition_to_id.required' => __('prototype.material.to_required'),
            'delivery_to_id.required' => __('prototype.material.delivery_required'), 'lines.*.item_id.required' => __('prototype.material.item_required'),
            'lines.*.req_qty.required' => __('prototype.material.qty_required'), 'lines.*.req_qty.gt' => __('prototype.material.qty_required'),
        ]);
        $data['programs'] = $data['programs'] ?? [];
        $data['lines'] = $data['lines'] ?? [];
        ksort($data['lines']);
        if (! empty($data['required_date']) && CarbonImmutable::parse($data['required_date'])->lt(CarbonImmutable::parse($data['req_date']))) {
            throw ValidationException::withMessages(['required_date' => __('prototype.material.required_before_date')]);
        }
        if ((! $row || $row->linesOpen()) && $data['lines'] === []) {
            throw ValidationException::withMessages(['lines' => __('prototype.material.lines_required')]);
        }
        if (! $row && Sample::query()->whereKey($data['sample_id'])->value('state') === 'CANCELLED') {
            throw ValidationException::withMessages(['sample_id' => __('prototype.material.sample_cancelled')]);
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function present(Collection $rows, bool $detail = false): array
    {
        $cards = SampleCards::of(Sample::query()->whereIn('id', $rows->pluck('sample_id'))->get());
        $lines = MaterialRequisitionLine::query()->whereIn('requisition_id', $rows->pluck('id'))->orderBy('sort_order')->get()->groupBy('requisition_id');
        $programs = MaterialRequisitionProgram::query()->whereIn('requisition_id', $rows->pluck('id'))->orderBy('sort_order')->get()->groupBy('requisition_id');
        $programRows = SampleProgram::query()->whereIn('id', $programs->flatten()->pluck('program_id'))->get(['id', 'program_no', 'program_date'])->keyBy('id');
        $stores = Store::query()->whereIn('id', [...$rows->pluck('requisition_to_id'), ...$rows->pluck('delivery_to_id'), ...$lines->flatten()->pluck('warehouse_id')->filter()])->get(['id', 'name', 'code'])->keyBy('id');
        $items = MaterialItem::query()->whereIn('id', $lines->flatten()->pluck('item_id'))->get(['id', 'item_code', 'name'])->keyBy('id');
        $departments = Department::query()->whereIn('id', $rows->pluck('department_id')->filter())->pluck('name', 'id');
        $names = Prototype::names([...$rows->pluck('requested_by'), ...$rows->pluck('dept_approved_by'), ...$rows->pluck('store_verified_by'), ...$rows->pluck('approved_by')]);
        $companyName = Company::query()->whereKey(app(TenantContext::class)->companyId())->value('name');
        $store = fn ($id) => ($s = $stores[$id] ?? null) ? $s->name : null;
        $stamp = fn ($by, $at) => $by ? trim(($names[$by] ?? '').' · '.$at?->format('d M Y'), ' ·') : null;

        return $rows->map(function (MaterialRequisition $r) use ($cards, $lines, $programs, $programRows, $stores, $items, $departments, $names, $companyName, $store, $stamp, $detail): array {
            $list = $lines[$r->id] ?? collect();
            $req = (float) $list->sum(fn ($l) => (float) $l->req_qty);
            $issued = (float) $list->sum(fn ($l) => (float) $l->issued_qty);
            $out = [
                'id' => $r->id, 'mr_no' => $r->mr_no, 'req_date' => $r->req_date->toDateString(), 'required_date' => $r->required_date?->toDateString(),
                'department_id' => $r->department_id, 'department' => $r->department_id ? ($departments[$r->department_id] ?? null) : null,
                'requested_by' => $r->requested_by, 'requested_by_name' => $names[$r->requested_by] ?? null, 'requisition_type' => $r->requisition_type,
                'requisition_to_id' => $r->requisition_to_id, 'requisition_to' => $store($r->requisition_to_id), 'delivery_to_id' => $r->delivery_to_id, 'delivery_to' => $store($r->delivery_to_id),
                'remarks' => $r->remarks, 'state' => $r->state, 'rejection_reason' => $r->rejection_reason,
                'dept_approved' => $stamp($r->dept_approved_by, $r->dept_approved_at), 'store_verified' => $stamp($r->store_verified_by, $r->store_verified_at), 'approved' => $stamp($r->approved_by, $r->approved_at),
                'programs_count' => ($programs[$r->id] ?? collect())->count(), 'items_count' => $list->count(),
                // A short line: the store holds less than asked, and nothing has been issued against it yet.
                'short_lines' => $list->filter(fn ($l) => $l->available_qty !== null && (float) $l->available_qty < (float) $l->req_qty && (float) $l->issued_qty == 0.0)->count(),
                'total_req' => round($req, 4), 'total_issued' => round($issued, 4), 'issued_pct' => $req > 0 ? round($issued / $req * 100, 1) : 0.0,
                'editable' => $r->isEditable(), 'lines_open' => $r->linesOpen(),
            ] + ($cards[$r->sample_id] ?? []);
            if ($detail) {
                $out['programs'] = ($programs[$r->id] ?? collect())->map(fn ($p) => ($g = $programRows[$p->program_id] ?? null) ? [
                    'program_id' => $g->id, 'program_no' => $g->program_no, 'company' => $companyName, 'program_date' => $g->program_date?->toDateString(),
                ] : null)->filter()->values()->all();
                $out['lines'] = $list->map(fn (MaterialRequisitionLine $l): array => [
                    'id' => $l->id, ...$l->only(['item_id', 'material_group', 'fabric_type_id', 'color', 'size', 'description', 'specification', 'unit', 'warehouse_id', 'remarks']),
                    'item' => ($i = $items[$l->item_id] ?? null) ? "{$i->name} ({$i->item_code})" : null, 'warehouse' => $l->warehouse_id ? (($s = $stores[$l->warehouse_id] ?? null) ? "{$s->name} ({$s->code})" : null) : null,
                    'req_qty' => (float) $l->req_qty, 'available_qty' => $l->available_qty === null ? null : (float) $l->available_qty, 'issued_qty' => (float) $l->issued_qty,
                    'short_qty' => $l->available_qty === null ? null : max(0.0, (float) $l->req_qty - (float) $l->available_qty),
                    'balance' => self::balance($l),
                ])->values()->all();
            }

            return $out;
        })->values()->all();
    }

    /** What is still to issue on a line: no more than asked, nor than the store verified it holds. */
    public static function balance(MaterialRequisitionLine $l): float
    {
        $cap = $l->available_qty === null ? (float) $l->req_qty : min((float) $l->req_qty, (float) $l->available_qty);

        return round(max(0.0, $cap - (float) $l->issued_qty), 4);
    }
}
