<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Http\Controllers\Api;

use App\Modules\GeneralStore\Models\GsDepartment;
use App\Modules\GeneralStore\Models\GsItem;
use App\Modules\GeneralStore\Models\GsRequisition;
use App\Modules\GeneralStore\Models\GsRequisitionLine;
use App\Modules\GeneralStore\Models\GsStore;
use App\Modules\GeneralStore\Models\GsUnit;
use App\Modules\GeneralStore\Support\GeneralStoreDefaults;
use App\Modules\GeneralStore\Support\GsBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * General Store → Requisition (AnnotechERP 10/04–10/05), as Brickland keeps
 * it. A department asks the store for items: a draft it may edit, then
 * submitted; the Store Manager approves it line by line (a quantity per
 * item, with a note) or rejects it with a reason; a draft or a pending
 * requisition may be cancelled. The store issues against an approved one
 * (Stock Issue), in part or in full, which moves it to Partially Issued and
 * Issued. With approval switched off in Settings, a submitted requisition is
 * approved as asked.
 */
class GsRequisitionApiController extends ApiController
{
    /** Which requisitions each screen lists. */
    private const LISTS = ['pending' => ['PENDING'], 'approved' => ['APPROVED', 'PARTIAL'], 'issued' => ['ISSUED']];

    /** What the New Requisition form picks from. */
    public function options(): JsonResponse
    {
        $this->allow('store.requisition.create');
        $company = app(TenantContext::class)->companyId();
        GeneralStoreDefaults::departments($company);
        GeneralStoreDefaults::stores($company);
        $items = GsItem::query()->where('active', true)->orderBy('name')->get();
        $stock = GsBook::stock($items->pluck('id'));
        $units = GsUnit::query()->pluck('code', 'id');

        return ApiResponse::ok([
            'departments' => GsDepartment::query()->where('active', true)->orderBy('name')->get()->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->all(),
            'stores' => GsStore::query()->where('active', true)->get()->sortBy(fn (GsStore $s) => [! $s->is_default, $s->name])->values()
                ->map(fn (GsStore $s) => ['value' => $s->id, 'label' => $s->name, 'is_default' => $s->is_default])->all(),
            'items' => $items->map(fn (GsItem $i) => [
                'value' => $i->id, 'label' => "{$i->name} ({$i->item_code})", 'code' => $i->item_code, 'unit' => $units[$i->gs_unit_id] ?? '',
                'stock' => (object) array_filter($stock[$i->id] ?? [], fn ($q) => abs($q) > 0.00005),
            ])->values()->all(),
            'approval' => GsBook::settings()->requisition_approval, 'voucher' => GsBook::voucher(),
        ]);
    }

    /** A screen's list: mine (New Requisition), pending, approved or issued. */
    public function index(Request $request): JsonResponse
    {
        $list = $request->query('list', 'mine');
        match ($list) {
            'mine' => $this->allow('store.requisition.create'),
            'pending' => $this->allow('store.requisition.approve'),
            'approved', 'issued' => $this->allowAny(['store.requisition.approve', 'store.inventory.post', 'store.inventory.view']),
            default => abort(404),
        };
        $reqs = GsRequisition::query()
            ->when($list === 'mine', fn ($q) => $q->where('requested_by', $this->caller()->auditUserId()))
            ->when($list !== 'mine', fn ($q) => $q->whereIn('status', self::LISTS[$list]))
            ->orderByDesc('req_date')->orderByDesc('req_no')->limit(500)->get();

        return ApiResponse::ok(['rows' => $this->rows($reqs)]);
    }

    public function show(string $requisition): JsonResponse
    {
        $this->allowAny(['store.requisition.create', 'store.requisition.approve', 'store.inventory.post', 'store.inventory.view']);

        return ApiResponse::ok($this->payload($this->find($requisition)));
    }

    /** Save Draft or Submit a new requisition. */
    public function store(Request $request): JsonResponse
    {
        $this->allow('store.requisition.create');
        $data = $this->validated($request);
        $req = DB::transaction(function () use ($data): GsRequisition {
            $req = GsRequisition::query()->create([
                'req_no' => GsBook::nextRequisitionNumber($data['req_date']), 'req_date' => $data['req_date'], 'gs_department_id' => $data['gs_department_id'],
                'gs_store_id' => $data['gs_store_id'], 'reason' => $data['reason'] ?? null, 'status' => 'DRAFT', 'requested_by' => $this->caller()->auditUserId(),
            ]);
            $this->writeLines($req, $data['lines']);
            GsBook::audit('CREATE', 'gs-req-new', $req->req_no, __('store.req.audit.draft', ['no' => $req->req_no], 'en'));

            return $req;
        });
        if ($data['submit']) {
            $this->doSubmit($req);
        }

        return ApiResponse::created($this->payload($req->fresh()));
    }

    /** Edit a draft — Save Draft again, or Submit. */
    public function update(Request $request, string $requisition): JsonResponse
    {
        $this->allow('store.requisition.create');
        $req = $this->ownDraft($requisition);
        $data = $this->validated($request);
        DB::transaction(function () use ($req, $data): void {
            $req->update(['req_date' => $data['req_date'], 'gs_department_id' => $data['gs_department_id'], 'gs_store_id' => $data['gs_store_id'], 'reason' => $data['reason'] ?? null]);
            GsRequisitionLine::query()->where('gs_requisition_id', $req->id)->delete();
            $this->writeLines($req, $data['lines']);
        });
        if ($data['submit']) {
            $this->doSubmit($req->fresh());
        }

        return ApiResponse::ok($this->payload($req->fresh()));
    }

    /** Submit a draft from the list. */
    public function submit(string $requisition): JsonResponse
    {
        $this->allow('store.requisition.create');
        $req = $this->ownDraft($requisition);
        $this->doSubmit($req);

        return ApiResponse::ok($this->payload($req->fresh()));
    }

    /** Approve: a quantity for every line (none above what was asked), and a note. */
    public function approve(Request $request, string $requisition): JsonResponse
    {
        $this->allow('store.requisition.approve');
        $req = $this->pending($requisition);
        $data = $request->validate([
            'lines' => ['required', 'array'], 'lines.*.line_id' => ['required', 'string'], 'lines.*.approved_qty' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $lines = GsRequisitionLine::query()->where('gs_requisition_id', $req->id)->orderBy('sort_order')->get()->keyBy('id');
        $given = collect($data['lines'])->keyBy('line_id');
        $units = $this->unitsOf($lines->pluck('gs_item_id'));
        foreach ($lines->values() as $i => $l) {
            $q = (float) ($given[$l->id]['approved_qty'] ?? 0);
            if ($q > (float) $l->requested_qty + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.approved_qty" => __('store.req.v.approve_over', ['n' => $i + 1, 'qty' => GsBook::qty((float) $l->requested_qty), 'unit' => $units[$l->gs_item_id] ?? ''])]);
            }
        }
        if ($lines->sum(fn ($l) => (float) ($given[$l->id]['approved_qty'] ?? 0)) <= 0.00005) {
            throw ValidationException::withMessages(['lines' => __('store.req.v.approve_none')]);
        }
        DB::transaction(function () use ($req, $lines, $given, $data): void {
            foreach ($lines as $l) {
                $l->update(['approved_qty' => round((float) ($given[$l->id]['approved_qty'] ?? 0), 4)]);
            }
            $req->update(['status' => 'APPROVED', 'decided_by' => $this->caller()->auditUserId(), 'decided_at' => now(), 'approval_note' => $data['note'] ?? null]);
            GsBook::audit('UPDATE', 'gs-req-pending', $req->req_no, __('store.req.audit.approved', ['no' => $req->req_no], 'en'));
        });

        return ApiResponse::ok($this->payload($req->fresh()));
    }

    /** Reject, with the reason the department will see. */
    public function reject(Request $request, string $requisition): JsonResponse
    {
        $this->allow('store.requisition.approve');
        $req = $this->pending($requisition);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], ['reason.required' => __('store.req.v.reason')]);
        $req->update(['status' => 'REJECTED', 'decided_by' => $this->caller()->auditUserId(), 'decided_at' => now(), 'closing_reason' => $data['reason']]);
        GsBook::audit('UPDATE', 'gs-req-pending', $req->req_no, __('store.req.audit.rejected', ['no' => $req->req_no, 'reason' => $data['reason']], 'en'));

        return ApiResponse::ok($this->payload($req->fresh()));
    }

    /** Cancel a draft or a pending requisition: its own requester, or the Store Manager. It stays on record. */
    public function cancel(Request $request, string $requisition): JsonResponse
    {
        $req = $this->find($requisition);
        $mine = $req->requested_by === $this->caller()->auditUserId();
        if (! ($mine && $this->caller()->can('store.requisition.create'))) {
            $this->allow('store.requisition.approve');
        }
        if (! in_array($req->status, ['DRAFT', 'PENDING'], true)) {
            throw ValidationException::withMessages(['requisition' => __('store.req.v.not_cancellable', ['no' => $req->req_no])]);
        }
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $req->update(['status' => 'CANCELLED', 'cancelled_by' => $this->caller()->auditUserId(), 'cancelled_at' => now(), 'closing_reason' => $data['reason'] ?? null]);
        GsBook::audit('UPDATE', $request->input('screen') === 'gs-req-pending' ? 'gs-req-pending' : 'gs-req-new', $req->req_no, __('store.req.audit.cancelled', ['no' => $req->req_no], 'en'));

        return ApiResponse::ok($this->payload($req->fresh()));
    }

    /** A print of the requisition, on the audit trail as Brickland keeps it. */
    public function printed(Request $request, string $requisition): JsonResponse
    {
        $this->allowAny(['store.requisition.create', 'store.requisition.approve', 'store.inventory.post', 'store.inventory.view']);
        $req = $this->find($requisition);
        $screen = in_array($request->input('screen'), ['gs-req-new', 'gs-req-pending', 'gs-req-approved', 'gs-req-issued'], true) ? $request->input('screen') : 'gs-req-new';
        GsBook::audit('REPORT_EXPORT', $screen, $req->req_no, __('store.req.audit.printed', ['no' => $req->req_no], 'en'));

        return ApiResponse::ok(['printed' => true]);
    }

    /** What the store may still issue against an approved requisition. */
    public function issuable(string $requisition): JsonResponse
    {
        $this->allow('store.inventory.post');
        $req = $this->find($requisition);
        if (! in_array($req->status, GsRequisition::ISSUABLE, true)) {
            throw ValidationException::withMessages(['requisition' => __('store.req.v.not_issuable', ['no' => $req->req_no])]);
        }
        $issued = GsBook::issuedAgainst($req);
        $lines = GsRequisitionLine::query()->where('gs_requisition_id', $req->id)->where('approved_qty', '>', 0)->orderBy('sort_order')->get();

        return ApiResponse::ok([
            'req_no' => $req->req_no, 'store_id' => $req->gs_store_id, 'department_id' => $req->gs_department_id,
            'requested_by' => DB::table('users')->where('id', $req->requested_by)->value('name'),
            'lines' => $lines->map(fn (GsRequisitionLine $l) => [
                'item_id' => $l->gs_item_id, 'approved' => (float) $l->approved_qty, 'issued' => $issued[$l->gs_item_id] ?? 0.0,
                'remaining' => round(max(0, (float) $l->approved_qty - ($issued[$l->gs_item_id] ?? 0)), 4),
            ])->values()->all(),
        ]);
    }

    /** @return array{req_date: string, gs_department_id: string, gs_store_id: string, reason: ?string, submit: bool, lines: list<array{gs_item_id: string, quantity: float, remarks: ?string}>} */
    private function validated(Request $request): array
    {
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'req_date' => ['required', 'date'],
            'gs_department_id' => ['required', Rule::exists('gs_departments', 'id')->where('company_id', $company)],
            'gs_store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'reason' => ['nullable', 'string', 'max:2000'], 'submit' => ['boolean'],
            'lines' => ['present', 'array', 'max:200'], 'lines.*.gs_item_id' => ['nullable', 'string'], 'lines.*.quantity' => ['nullable'],
            'lines.*.remarks' => ['nullable', 'string', 'max:500'],
        ], [
            'req_date.required' => __('store.inv.v.date'), 'gs_department_id.required' => __('store.inv.v.department'), 'gs_store_id.required' => __('store.inv.v.store'),
        ]);
        ksort($data['lines']);
        $given = collect(array_values($data['lines']))->map(fn ($l) => is_array($l) ? $l : [])
            ->filter(fn ($l) => trim((string) ($l['gs_item_id'] ?? '')) !== '' || trim((string) ($l['quantity'] ?? '')) !== '')->values();
        if ($given->filter(fn ($l) => trim((string) ($l['gs_item_id'] ?? '')) !== '')->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('store.inv.v.pick_item')]);
        }
        $items = GsItem::query()->where('active', true)->whereIn('id', $given->pluck('gs_item_id')->filter()->all())->pluck('id')->flip();
        $errors = [];
        $lines = [];
        foreach ($given as $i => $l) {
            if (! isset($items[$l['gs_item_id'] ?? ''])) {
                $errors["lines.{$i}.gs_item_id"] = __('store.inv.v.line_item', ['n' => $i + 1]);
            } elseif (! is_numeric($l['quantity'] ?? null) || (float) $l['quantity'] <= 0) {
                $errors["lines.{$i}.quantity"] = __('store.req.v.line_qty', ['n' => $i + 1]);
            } else {
                $lines[] = ['gs_item_id' => $l['gs_item_id'], 'quantity' => round((float) $l['quantity'], 4), 'remarks' => trim((string) ($l['remarks'] ?? '')) ?: null];
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return ['req_date' => $data['req_date'], 'gs_department_id' => $data['gs_department_id'], 'gs_store_id' => $data['gs_store_id'], 'reason' => $data['reason'] ?? null, 'submit' => (bool) ($data['submit'] ?? false), 'lines' => $lines];
    }

    private function writeLines(GsRequisition $req, array $lines): void
    {
        foreach ($lines as $i => $l) {
            GsRequisitionLine::query()->create(['gs_requisition_id' => $req->id, 'gs_item_id' => $l['gs_item_id'], 'requested_qty' => $l['quantity'], 'remarks' => $l['remarks'], 'sort_order' => $i + 1]);
        }
    }

    /** Submitted: to the Store Manager — or, with approval switched off, approved as asked. */
    private function doSubmit(GsRequisition $req): void
    {
        DB::transaction(function () use ($req): void {
            if (GsBook::settings()->requisition_approval) {
                $req->update(['status' => 'PENDING', 'submitted_at' => now()]);
            } else {
                GsRequisitionLine::query()->where('gs_requisition_id', $req->id)->update(['approved_qty' => DB::raw('requested_qty')]);
                $req->update(['status' => 'APPROVED', 'submitted_at' => now(), 'decided_at' => now(), 'approval_note' => __('store.req.auto_approved')]);
            }
            GsBook::audit('CREATE', 'gs-req-new', $req->req_no, __('store.req.audit.submitted', ['no' => $req->req_no], 'en'));
        });
    }

    private function ownDraft(string $no): GsRequisition
    {
        $req = $this->find($no);
        if ($req->status !== 'DRAFT') {
            throw ValidationException::withMessages(['requisition' => __('store.req.v.not_draft', ['no' => $req->req_no])]);
        }
        if ($req->requested_by !== $this->caller()->auditUserId()) {
            throw ValidationException::withMessages(['requisition' => __('store.req.v.not_yours', ['no' => $req->req_no])]);
        }

        return $req;
    }

    private function pending(string $no): GsRequisition
    {
        $req = $this->find($no);
        if ($req->status !== 'PENDING') {
            throw ValidationException::withMessages(['requisition' => __('store.req.v.not_pending', ['no' => $req->req_no])]);
        }

        return $req;
    }

    private function find(string $no): GsRequisition
    {
        return GsRequisition::query()->where('req_no', strtoupper(trim($no)))->first()
            ?? throw ValidationException::withMessages(['requisition' => __('store.req.v.missing', ['no' => strtoupper(trim($no))])]);
    }

    /** @param list<string> $permissions */
    private function allowAny(array $permissions): void
    {
        if (! collect($permissions)->contains(fn ($p) => $this->caller()->can($p))) {
            $this->allow($permissions[0]);
        }
    }

    /** @return array<string, string> item id => unit code */
    private function unitsOf($itemIds): array
    {
        $units = GsUnit::query()->pluck('code', 'id');

        return GsItem::query()->whereIn('id', $itemIds)->get()->mapWithKeys(fn (GsItem $i) => [$i->id => $units[$i->gs_unit_id] ?? ''])->all();
    }

    /** @return list<array<string, mixed>> */
    private function rows($reqs): array
    {
        $lines = GsRequisitionLine::query()->whereIn('gs_requisition_id', $reqs->pluck('id'))->orderBy('sort_order')->get()->groupBy('gs_requisition_id');
        $items = GsItem::query()->whereIn('id', $lines->flatten()->pluck('gs_item_id')->unique())->pluck('name', 'id');
        $depts = GsDepartment::query()->pluck('name', 'id');
        $stores = GsStore::query()->pluck('name', 'id');
        $names = DB::table('users')->whereIn('id', $reqs->pluck('requested_by')->filter()->unique())->pluck('name', 'id');

        return $reqs->map(function (GsRequisition $r) use ($lines, $items, $depts, $stores, $names): array {
            $set = $lines[$r->id] ?? collect();
            $first = $set->first();
            $approved = $set->whereNotNull('approved_qty');

            return [
                'req_no' => $r->req_no, 'date' => $r->req_date->toDateString(), 'department' => $depts[$r->gs_department_id] ?? null, 'store' => $stores[$r->gs_store_id] ?? null,
                'items' => $first ? ($items[$first->gs_item_id] ?? '').($set->count() > 1 ? ' +'.($set->count() - 1) : '') : '',
                'requested' => round((float) $set->sum('requested_qty'), 4), 'approved' => $approved->isEmpty() ? null : round((float) $approved->sum('approved_qty'), 4),
                'issued' => round(array_sum(GsBook::issuedAgainst($r)), 4), 'requested_by' => $names[$r->requested_by] ?? null, 'status' => $r->status,
                'mine' => $r->requested_by === $this->caller()->auditUserId(),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function payload(GsRequisition $r): array
    {
        $lines = GsRequisitionLine::query()->where('gs_requisition_id', $r->id)->orderBy('sort_order')->get();
        $items = GsItem::query()->whereIn('id', $lines->pluck('gs_item_id'))->get()->keyBy('id');
        $units = GsUnit::query()->pluck('code', 'id');
        $stock = GsBook::stock($lines->pluck('gs_item_id'));
        $issued = GsBook::issuedAgainst($r);
        $names = DB::table('users')->whereIn('id', array_filter([$r->requested_by, $r->decided_by, $r->cancelled_by]))->pluck('name', 'id');

        return [
            'req_no' => $r->req_no, 'date' => $r->req_date->toDateString(), 'status' => $r->status, 'department_id' => $r->gs_department_id, 'store_id' => $r->gs_store_id,
            'department' => GsDepartment::query()->whereKey($r->gs_department_id)->value('name'), 'store' => GsStore::query()->whereKey($r->gs_store_id)->value('name'),
            'requested_by' => $names[$r->requested_by] ?? null, 'reason' => $r->reason, 'submitted_at' => $r->submitted_at?->toIso8601String(),
            'decided_by' => $names[$r->decided_by] ?? null, 'decided_at' => $r->decided_at?->toIso8601String(), 'approval_note' => $r->approval_note,
            'closing_reason' => $r->closing_reason, 'cancelled_by' => $names[$r->cancelled_by] ?? null, 'cancelled_at' => $r->cancelled_at?->toIso8601String(),
            'mine' => $r->requested_by === $this->caller()->auditUserId(),
            'lines' => $lines->map(fn (GsRequisitionLine $l) => [
                'id' => $l->id, 'sl' => $l->sort_order, 'item_id' => $l->gs_item_id, 'item_code' => $items[$l->gs_item_id]->item_code ?? null, 'item' => $items[$l->gs_item_id]->name ?? null,
                'unit' => $units[$items[$l->gs_item_id]->gs_unit_id ?? ''] ?? null, 'requested' => (float) $l->requested_qty,
                'approved' => $l->approved_qty === null ? null : (float) $l->approved_qty, 'issued' => $issued[$l->gs_item_id] ?? 0.0,
                'remaining' => $l->approved_qty === null ? null : round(max(0, (float) $l->approved_qty - ($issued[$l->gs_item_id] ?? 0)), 4),
                'in_store' => round((float) ($stock[$l->gs_item_id][$r->gs_store_id] ?? 0), 4), 'remarks' => $l->remarks,
            ])->values()->all(),
        ];
    }
}
