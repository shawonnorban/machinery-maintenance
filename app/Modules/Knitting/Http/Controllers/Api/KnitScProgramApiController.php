<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitScProgram;
use App\Modules\Knitting\Models\KnitScProgramLine;
use App\Modules\Knitting\Models\KnitScReceipt;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
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
 * Knit Production → Production (AnnotechERP 15/04) → the three S/C knitting
 * programmes, as Brickland works them: Bulk (Solid), Sample (Solid) and
 * Party (Y/D). A programme (SCP-…) asks one sub-contract knitter for fabric,
 * one line per fabric and shade with the kilograms and the rate per kilogram;
 * the form only writes what is asked. Submit sends it to the approver and
 * fixes its lines; Approve releases it to the party; Reject sends it back to
 * the desk to be put right and submitted again. What comes back against each
 * line is kept by the receive desk, not typed here.
 */
class KnitScProgramApiController extends ApiController
{
    /** Everything both S/C desks pick from. */
    public function options(): JsonResponse
    {
        $this->allow('knitting.production.view');
        $company = app(TenantContext::class)->companyId();
        $o = KnitProduction::options();
        $refs = DB::table('knit_ref_entries')->where('company_id', $company)->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'list', 'name']);

        return ApiResponse::ok(collect($o)->only(['company', 'buyers', 'orders', 'categories', 'fabric_types', 'colours', 'parties'])->all() + [
            'colour_groups' => YarnProcurement::COLOURS,
            'uoms' => array_map(fn (array $u) => ['value' => $u['code'], 'label' => $u['code']], UomCodes::options($company)),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->values()->all(),
            'compositions' => DB::table('fiber_compositions')->where('company_id', $company)->where('active', true)->orderBy('description')->get(['id', 'description'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->description])->values()->all(),
            'counts' => KnitYarnCount::query()->where('active', true)->orderBy('display_order')->orderBy('description')->get()->map(fn (KnitYarnCount $c) => ['value' => $c->id, 'label' => (string) $c->description])->values()->all(),
            'mc_dias' => $refs->where('list', 'DIA')->map(fn ($r) => ['value' => $r->id, 'label' => $r->name])->values()->all(),
            'gauges' => $refs->where('list', 'GG')->map(fn ($r) => ['value' => $r->id, 'label' => $r->name])->values()->all(),
            'types' => KnitScProgram::TYPES, 'statuses' => KnitScProgram::STATUSES,
            'receipt_statuses' => KnitScReceipt::STATUSES, 'yd_types' => KnitScReceipt::YD_TYPES, 'rate_types' => KnitScReceipt::RATE_TYPES,
            'programs' => KnitScProgram::query()->orderByDesc('program_no')->limit(500)->get(['program_no', 'kind', 'po_date', 'party_id', 'status'])
                ->map(fn (KnitScProgram $p) => ['value' => $p->program_no, 'kind' => $p->kind, 'label' => $p->program_no.' — '.$p->po_date->format('d M Y').' · '.__("knitting.sc.status.{$p->status}")])->values()->all(),
            'receipts' => KnitScReceipt::query()->orderByDesc('prog_date')->orderByDesc('program_no')->limit(500)->get(['program_no', 'prog_date', 'status'])
                ->map(fn (KnitScReceipt $r) => ['value' => $r->program_no, 'label' => $r->program_no.' — '.$r->prog_date->format('d M Y').' · '.__("knitting.sc.receipt_status.{$r->status}")])->values()->all(),
        ]);
    }

    public function index(Request $request, string $kind): JsonResponse
    {
        $this->allow('knitting.production.view');
        $f = $request->validate([
            'category' => ['nullable', 'string', 'max:16'], 'party' => ['nullable', 'string'], 'buyer' => ['nullable', 'string'], 'style' => ['nullable', 'string', 'max:64'],
            'q' => ['nullable', 'string', 'max:64'], 'status' => ['nullable', Rule::in(KnitScProgram::STATUSES)], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);
        $rows = KnitScProgram::query()->where('kind', $kind)
            ->when(! empty($f['category']), fn ($q) => $q->where('category_code', $f['category']))
            ->when(! empty($f['party']), fn ($q) => $q->where('party_id', $f['party']))
            ->when(! empty($f['buyer']), fn ($q) => $q->where('buyer_id', $f['buyer']))
            ->when(! empty($f['style']), fn ($q) => $q->where('work_style_no', 'ilike', '%'.$f['style'].'%'))
            ->when(! empty($f['q']), fn ($q) => $q->where('program_no', 'ilike', '%'.$f['q'].'%'))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('po_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('po_date', '<=', $f['to']))
            ->orderByDesc('po_date')->orderByDesc('program_no')->limit(300)->get();
        $qty = KnitScProgramLine::query()->whereIn('program_id', $rows->pluck('id'))->groupBy('program_id')->selectRaw('program_id, sum(qty) as q')->pluck('q', 'program_id');
        $names = $this->names($rows);

        return ApiResponse::ok($rows->map(fn (KnitScProgram $p) => [
            'program_no' => $p->program_no, 'program_type' => $p->program_type, 'party' => $names['parties'][$p->party_id] ?? null,
            'party_id' => $p->party_id, 'buyer_id' => $p->buyer_id, 'category_code' => $p->category_code,
            'po_date' => $p->po_date->toDateString(), 'transferred' => false, 'qty' => round((float) ($qty[$p->id] ?? 0), 3),
            'buyer' => $names['buyers'][$p->buyer_id] ?? null, 'work_style_no' => $p->work_style_no, 'status' => $p->status,
        ])->values()->all());
    }

    public function show(string $kind, string $no): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->present($this->find($kind, $no)));
    }

    public function save(Request $request, string $kind, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $no ? $this->find($kind, $no) : null;
        if ($row && ! in_array($row->status, ['DRAFT', 'REJECTED'], true)) {
            throw ValidationException::withMessages(['status' => __('knitting.sc.v.fixed', ['no' => $row->program_no, 'status' => __("knitting.sc.status.{$row->status}")])]);
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'po_date' => ['required', 'date'], 'target_date' => ['nullable', 'date', 'after_or_equal:po_date'],
            'program_type' => ['required', Rule::in(KnitScProgram::TYPES[$kind])],
            'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'category_code' => ['nullable', Rule::in(array_column(KnitProduction::categories(), 'value'))],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'style_ref' => ['nullable', 'string', 'max:64'],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'work_style_no' => ['nullable', 'string', 'max:64'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:200'],
            'lines.*.fabric' => ['required', 'string', 'max:160'],
            'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'lines.*.colour_group' => ['nullable', Rule::in(YarnProcurement::COLOURS)],
            'lines.*.machine_dia' => ['nullable', 'string', 'max:24'], 'lines.*.gauge' => ['nullable', 'string', 'max:24'],
            'lines.*.finish_dia' => ['nullable', 'string', 'max:24'], 'lines.*.gsm' => ['nullable', 'string', 'max:24'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'po_date.required' => __('knitting.sc.v.po_date'), 'program_type.required' => __('knitting.sc.v.type'), 'party_id.required' => __('knitting.sc.v.party'),
            'lines.*.fabric.required' => __('knitting.sc.v.fabric'), 'lines.*.qty.required' => __('knitting.sc.v.qty'), 'lines.*.qty.gt' => __('knitting.sc.v.qty'),
            'lines.*.uom.required' => __('knitting.sc.v.uom'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.sc.v.no_lines')]);
        }
        // The order, when one is chosen, names the buyer and the job/work style.
        if (! empty($data['order_id'])) {
            $order = Order::query()->findOrFail($data['order_id']);
            $data['buyer_id'] ??= $order->buyer_id;
            $data['work_style_no'] = $order->work_style_no ?: $order->style_no ?: ($data['work_style_no'] ?? null);
            $data['style_ref'] ??= $order->style_no;
        }
        $row = DB::transaction(function () use ($row, $kind, $data, $lines): KnitScProgram {
            $head = collect($data)->only(['po_date', 'target_date', 'program_type', 'party_id', 'category_code', 'buyer_id', 'style_ref', 'order_id', 'work_style_no', 'remarks'])
                ->map(fn ($v) => $v === '' ? null : $v)->all() + ['target_date' => null, 'category_code' => null, 'buyer_id' => null, 'style_ref' => null, 'order_id' => null, 'work_style_no' => null, 'remarks' => null];
            $row ??= KnitScProgram::query()->create($head + [
                'kind' => $kind, 'program_no' => KnitProduction::nextNumber('SCP', 'knit_sc_programs', 'program_no', $data['po_date']),
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitScProgramLine::query()->where('program_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitScProgramLine::query()->create([
                    'company_id' => $row->company_id, 'program_id' => $row->id, 'sort_order' => $i + 1, 'fabric' => trim($l['fabric']),
                    'colour_id' => $l['colour_id'] ?? null, 'colour_group' => $l['colour_group'] ?? null, 'machine_dia' => $l['machine_dia'] ?? null,
                    'gauge' => $l['gauge'] ?? null, 'finish_dia' => $l['finish_dia'] ?? null, 'gsm' => $l['gsm'] ?? null, 'qty' => (float) $l['qty'],
                    'rate' => isset($l['rate']) && $l['rate'] !== '' ? (float) $l['rate'] : null, 'uom' => UomCodes::normalize($l['uom']), 'remarks' => $l['remarks'] ?? null,
                ]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->present($row->refresh())) : ApiResponse::created($this->present($row->refresh()));
    }

    /** Submit (Draft or Rejected → Submitted), Approve or Reject (Submitted → …). */
    public function transition(Request $request, string $kind, string $no, string $action): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->find($kind, $no);
        [$from, $to] = match ($action) {
            'submit' => [['DRAFT', 'REJECTED'], 'SUBMITTED'],
            'approve' => [['SUBMITTED'], 'APPROVED'],
            'reject' => [['SUBMITTED'], 'REJECTED'],
        };
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.sc.v.cannot', ['no' => $row->program_no, 'status' => __("knitting.sc.status.{$row->status}"), 'action' => __("knitting.sc.action.{$action}")])]);
        }
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;
        $row->update($action === 'submit'
            ? ['status' => $to, 'decision_note' => null, 'decided_at' => null, 'decided_by' => null]
            : ['status' => $to, 'decision_note' => $note, 'decided_at' => now(), 'decided_by' => $this->caller()->auditUserId()]);

        return ApiResponse::ok($this->present($row->refresh()) + ['message' => __("knitting.sc.done.{$action}", ['no' => $row->program_no])]);
    }

    /** A draft that was never submitted can be thrown away. */
    public function destroy(string $kind, string $no): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->find($kind, $no);
        if ($row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('knitting.sc.v.not_draft', ['no' => $row->program_no, 'status' => __("knitting.sc.status.{$row->status}")])]);
        }
        $row->delete();

        return ApiResponse::ok(['message' => __('knitting.sc.done.delete', ['no' => $no])]);
    }

    private function find(string $kind, string $no): KnitScProgram
    {
        return KnitScProgram::query()->where('kind', $kind)->where('program_no', $no)->firstOrFail();
    }

    /** @return array{parties: Collection, buyers: Collection} */
    private function names(Collection $rows): array
    {
        return [
            'parties' => DB::table('mp_suppliers')->whereIn('id', $rows->pluck('party_id')->filter()->unique())->pluck('name', 'id'),
            'buyers' => Buyer::query()->whereIn('id', $rows->pluck('buyer_id')->filter()->unique())->get(['id', 'name', 'short_name'])->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]),
        ];
    }

    /** @return array<string, mixed> */
    private function present(KnitScProgram $p): array
    {
        $names = $this->names(collect([$p]));
        $party = DB::table('mp_suppliers')->where('id', $p->party_id)->first(['name', 'code', 'address']);
        $colours = DB::table('colours')->whereIn('id', KnitScProgramLine::query()->where('program_id', $p->id)->pluck('colour_id')->filter())->get(['id', 'code', 'name'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->code ? "{$c->name} ({$c->code})" : $c->name]);
        $category = collect(KnitProduction::categories())->firstWhere('value', $p->category_code);
        $order = $p->order_id ? Order::query()->find($p->order_id) : null;

        return [
            'id' => $p->id, 'program_no' => $p->program_no, 'kind' => $p->kind, 'program_type' => $p->program_type, 'status' => $p->status,
            'po_date' => $p->po_date->toDateString(), 'target_date' => $p->target_date?->toDateString(),
            'party_id' => $p->party_id, 'party' => $party?->name, 'party_address' => $party?->address ?? null,
            'category_code' => $p->category_code, 'category' => $category['label'] ?? null,
            'buyer_id' => $p->buyer_id, 'buyer' => $names['buyers'][$p->buyer_id] ?? null, 'style_ref' => $p->style_ref,
            'order_id' => $p->order_id, 'order' => KnitProduction::orderLabel($order), 'work_style_no' => $p->work_style_no, 'remarks' => $p->remarks,
            'company' => DB::table('companies')->where('id', $p->company_id)->value('name'), 'transferred' => false,
            'decision_note' => $p->decision_note, 'decided_at' => $p->decided_at?->toIso8601String(),
            'lines' => KnitScProgramLine::query()->where('program_id', $p->id)->orderBy('sort_order')->get()->map(fn (KnitScProgramLine $l) => [
                'fabric' => $l->fabric, 'colour_id' => $l->colour_id, 'colour' => $colours[$l->colour_id] ?? null, 'colour_group' => $l->colour_group,
                'machine_dia' => $l->machine_dia, 'gauge' => $l->gauge, 'finish_dia' => $l->finish_dia, 'gsm' => $l->gsm, 'qty' => $l->qty, 'rate' => $l->rate,
                'uom' => $l->uom, 'received_qty' => $l->received_qty, 'remarks' => $l->remarks,
            ])->values()->all(),
        ];
    }
}
