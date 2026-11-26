<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\YarnComparison;
use App\Modules\Knitting\Models\YarnComparisonLine;
use App\Modules\Knitting\Models\YarnOffer;
use App\Modules\Knitting\Models\YarnRequisition;
use App\Modules\Knitting\Models\YarnRequisitionLine;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Yarn Procurement (AnnotechERP 15/02) → Yarn Comparative
 * Statement, as Brickland works it: an approved requisition picked off the
 * list fills the Details Data grid with its lines, present stock and open
 * pipeline (Qty in Kg is what is worth ordering after them); the spinners'
 * offers go below, one row per spinner per line per price band, quoted
 * against a line's SL #. Numbered YCS-2026100001; Finalize (from the list)
 * fixes which spinner each line is bought from, and a finalized sheet takes
 * no more edits. A comparison travels by its number.
 */
class YarnComparisonApiController extends ApiController
{
    /** The requisitions a comparison is raised against. */
    private const COMPARABLE = ['APPROVED', 'ORDERED'];

    public function options(): JsonResponse
    {
        $this->allow('knitting.yarn.view');
        $reqs = YarnRequisition::query()->whereIn('status', self::COMPARABLE)->orderByDesc('requisition_date')->orderByDesc('requisition_no')->get();
        $buyers = Buyer::query()->whereIn('id', $reqs->pluck('buyer_id')->filter())->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $lines = YarnRequisitionLine::query()->whereIn('requisition_id', $reqs->pluck('id'))->get()->groupBy('requisition_id');

        return ApiResponse::ok(YarnProcurement::options() + [
            // Search Requisition List: approved requisitions, each with its items and what it asks for.
            'requisitions' => $reqs->map(fn (YarnRequisition $r) => [
                'value' => $r->requisition_no, 'label' => $r->requisition_no, 'date' => $r->requisition_date->toDateString(), 'buyer' => $buyers[$r->buyer_id] ?? null,
                'buyer_id' => $r->buyer_id, 'items' => ($lines[$r->id] ?? collect())->count(),
                'required' => round((float) ($lines[$r->id] ?? collect())->sum(fn ($l) => (float) $l->qty_local + (float) $l->qty_import), 4),
            ])->values()->all(),
            'comparisons' => YarnComparison::query()->orderByDesc('cs_no')->limit(300)->pluck('cs_no')->map(fn ($n) => ['value' => $n, 'label' => $n])->values()->all(),
            'statuses' => YarnComparison::STATUSES,
        ]);
    }

    /** The register: nothing filled in lists the most recent comparisons. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('knitting.yarn.view');
        $f = $request->validate([
            'no' => ['nullable', 'string', 'max:24'], 'requisition' => ['nullable', 'string', 'max:24'], 'status' => ['nullable', Rule::in(YarnComparison::STATUSES)],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $rows = YarnComparison::query()
            ->when(! empty($f['no']), fn ($q) => $q->where('cs_no', 'ilike', '%'.addcslashes($f['no'], '%_\\').'%'))
            ->when(! empty($f['requisition']), fn ($q) => $q->whereIn('requisition_id', YarnRequisition::query()->where('requisition_no', $f['requisition'])->select('id')))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('cs_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('cs_date', '<=', $f['to']))
            ->orderByDesc('cs_date')->orderByDesc('cs_no')->limit(200)->get();
        $reqs = YarnRequisition::query()->whereIn('id', $rows->pluck('requisition_id'))->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $reqs->pluck('buyer_id')->filter())->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $lines = YarnComparisonLine::query()->whereIn('comparison_id', $rows->pluck('id'))->get()->groupBy('comparison_id');
        $offers = YarnOffer::query()->whereIn('comparison_id', $rows->pluck('id'))->get()->groupBy('comparison_id');
        $suppliers = MaterialSupplier::query()->pluck('name', 'id');

        return ApiResponse::ok($rows->map(function (YarnComparison $c) use ($reqs, $buyers, $lines, $offers, $suppliers) {
            $r = $reqs[$c->requisition_id] ?? null;
            $ls = $lines[$c->id] ?? collect();

            return [
                'cs_no' => $c->cs_no, 'cs_date' => $c->cs_date->toDateString(), 'requisition_no' => $r?->requisition_no, 'requisition_date' => $c->requisition_date?->toDateString(),
                'buyer' => $r ? ($buyers[$r->buyer_id] ?? null) : null, 'lines' => $ls->count(), 'offers' => ($offers[$c->id] ?? collect())->count(),
                'qty' => round((float) $ls->sum(fn ($l) => (float) $l->qty_local + (float) $l->qty_import), 4),
                'spinners' => $ls->pluck('supplier_id')->filter()->unique()->map(fn ($id) => $suppliers[$id] ?? null)->filter()->implode(', '),
                'status' => $c->status,
            ];
        })->values()->all());
    }

    /** Picking an approved requisition fills the grid: its lines, present stock and open pipeline, SL # 1, 2, 3 … */
    public function source(string $requisition): JsonResponse
    {
        $this->allow('knitting.yarn.view');
        $req = YarnRequisition::query()->where('requisition_no', $requisition)->whereIn('status', self::COMPARABLE)->firstOrFail();
        $names = YarnProcurement::names();
        $lines = YarnRequisitionLine::query()->where('requisition_id', $req->id)->orderBy('sort_order')->get();

        return ApiResponse::ok([
            'requisition_no' => $req->requisition_no, 'requisition_date' => $req->requisition_date->toDateString(),
            'lines' => $lines->values()->map(fn (YarnRequisitionLine $l, int $i) => collect($l->only(YarnProcurement::YARN))->all() + [
                'sl_no' => $i + 1, 'composition' => $names['compositions'][$l->composition_id] ?? null, 'count' => $names['counts'][$l->yarn_count_id] ?? null,
                'knit_start_date' => $l->knit_start_date?->toDateString(), 'ship_date' => $l->ship_date?->toDateString(),
                'qty_local' => (float) $l->qty_local, 'qty_import' => (float) $l->qty_import, 'booking_price' => $l->booking_price !== null ? (float) $l->booking_price : null,
                'present_stock' => YarnProcurement::presentStock($l->composition_id, $l->yarn_count_id), 'pipeline' => YarnProcurement::pipeline($l->composition_id, $l->yarn_count_id),
                'buyer_id' => $req->buyer_id, 'supplier_id' => null,
            ])->all(),
        ]);
    }

    public function show(string $comparison): JsonResponse
    {
        $this->allow('knitting.yarn.view');

        return ApiResponse::ok(self::presentComparison($this->find($comparison)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('knitting.yarn.manage');
        [$data, $req] = $this->validated($request);
        $row = DB::transaction(function () use ($data, $req): YarnComparison {
            $row = YarnComparison::query()->create([
                'cs_no' => YarnProcurement::nextNumber('YCS', 'knit_yarn_comparisons', 'cs_no', $data['cs_date']), 'cs_date' => $data['cs_date'],
                'requisition_id' => $req->id, 'requisition_date' => $req->requisition_date, 'remarks' => $data['remarks'] ?? null, 'status' => 'DRAFT',
                'created_by' => $this->caller()->auditUserId(),
            ]);
            $this->write($row, $data);

            return $row;
        });

        return ApiResponse::created(self::presentComparison($row));
    }

    public function update(Request $request, string $comparison): JsonResponse
    {
        $this->allow('knitting.yarn.manage');
        $row = $this->find($comparison);
        $this->assertDraft($row);
        [$data, $req] = $this->validated($request);
        DB::transaction(function () use ($row, $data, $req): void {
            $row->update(['cs_date' => $data['cs_date'], 'requisition_id' => $req->id, 'requisition_date' => $req->requisition_date, 'remarks' => $data['remarks'] ?? null]);
            $this->write($row, $data);
        });

        return ApiResponse::ok(self::presentComparison($row->refresh()));
    }

    public function destroy(string $comparison): JsonResponse
    {
        $this->allow('knitting.yarn.manage');
        $row = $this->find($comparison);
        $this->assertDraft($row);
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /** From the list: Finalize (needs an offer, and a spinner on every line) or Cancel a draft. */
    public function transition(string $comparison, string $action): JsonResponse
    {
        $this->allow('knitting.yarn.manage');
        $row = $this->find($comparison);
        abort_unless(in_array($action, ['finalize', 'cancel'], true), 404);
        $this->assertDraft($row, $action);
        if ($action === 'finalize') {
            if (! YarnOffer::query()->where('comparison_id', $row->id)->exists()) {
                throw ValidationException::withMessages(['offers' => __('knitting.yarn.v.no_offers')]);
            }
            $open = YarnComparisonLine::query()->where('comparison_id', $row->id)->whereNull('supplier_id')->orderBy('sl_no')->pluck('sl_no');
            if ($open->isNotEmpty()) {
                throw ValidationException::withMessages(['lines' => __('knitting.yarn.v.unfinalized', ['sl' => $open->implode(', ')])]);
            }
        }
        $row->update(['status' => $action === 'finalize' ? 'FINALIZED' : 'CANCELLED'] + ($action === 'finalize' ? ['finalized_at' => now()] : []));

        return ApiResponse::ok(self::presentComparison($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    private function find(string $comparison): YarnComparison
    {
        return YarnComparison::query()->where('cs_no', $comparison)->firstOrFail();
    }

    private function assertDraft(YarnComparison $row, string $action = 'save'): void
    {
        if ($row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('knitting.yarn.v.cs_locked', ['no' => $row->cs_no, 'status' => __("knitting.yarn.cs_status.{$row->status}")])]);
        }
    }

    /** @return array{0: array<string, mixed>, 1: YarnRequisition} */
    private function validated(Request $request): array
    {
        $company = app(TenantContext::class)->companyId();
        $qty = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
        $data = $request->validate([
            'cs_date' => ['required', 'date'],
            'requisition_no' => ['required', 'string'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            ...YarnProcurement::lineRules('lines.*'),
            'lines.*.sl_no' => ['nullable', 'integer', 'min:1', 'max:999'],
            'lines.*.qty_local' => $qty, 'lines.*.qty_import' => $qty, 'lines.*.present_stock' => $qty, 'lines.*.pipeline' => $qty, 'lines.*.booking_price' => $qty,
            'lines.*.buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'lines.*.supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'offers' => ['present', 'array', 'max:500'],
            'offers.*.sl_no' => ['required', 'integer', 'min:1'],
            'offers.*.source' => ['required', Rule::in(YarnProcurement::SOURCES)],
            'offers.*.supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'offers.*.price' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'offers.*.currency_id' => ['nullable', Rule::exists('currencies', 'id')->where('company_id', $company)],
            'offers.*.conf_qty' => $qty,
            'offers.*.lead_days' => ['nullable', 'integer', 'min:0', 'max:999'],
            'offers.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'cs_date.required' => __('knitting.yarn.v.cs_date'), 'requisition_no.required' => __('knitting.yarn.v.cs_requisition'),
            'lines.required' => __('knitting.yarn.v.cs_no_lines'), 'lines.min' => __('knitting.yarn.v.cs_no_lines'),
            'offers.*.supplier_id.required' => __('knitting.yarn.v.offer_spinner'), 'offers.*.price.required' => __('knitting.yarn.v.offer_price'), 'offers.*.price.gt' => __('knitting.yarn.v.offer_price'),
        ]);
        $req = YarnRequisition::query()->where('requisition_no', $data['requisition_no'])->first();
        if (! $req || ! in_array($req->status, self::COMPARABLE, true)) {
            throw ValidationException::withMessages(['requisition_no' => __('knitting.yarn.v.cs_requisition')]);
        }
        // SL #: a blank one takes the next free number.
        $lines = array_values($data['lines']);
        $used = array_filter(array_map(fn ($l) => isset($l['sl_no']) ? (int) $l['sl_no'] : null, $lines));
        if (count($used) !== count(array_unique($used))) {
            throw ValidationException::withMessages(['lines' => __('knitting.yarn.v.sl_twice')]);
        }
        $next = 1;
        $errors = [];
        foreach ($lines as $i => &$l) {
            if (empty($l['sl_no'])) {
                while (in_array($next, $used, true)) {
                    $next++;
                }
                $l['sl_no'] = $next;
                $used[] = $next;
            }
            if (empty($l['uom'])) {
                $errors["lines.{$i}.uom"] = __('knitting.yarn.v.line_uom', ['n' => $i + 1]);
            }
        }
        unset($l);
        $sls = array_map(fn ($l) => (int) $l['sl_no'], $lines);
        foreach (array_values($data['offers']) as $i => $o) {
            if (! in_array((int) $o['sl_no'], $sls, true)) {
                $errors["offers.{$i}.sl_no"] = __('knitting.yarn.v.offer_sl', ['sl' => $o['sl_no']]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $data['lines'] = $lines;
        $data['offers'] = array_values($data['offers']);

        return [$data, $req];
    }

    private function write(YarnComparison $row, array $data): void
    {
        YarnComparisonLine::query()->where('comparison_id', $row->id)->delete();
        YarnOffer::query()->where('comparison_id', $row->id)->delete();
        foreach ($data['lines'] as $l) {
            YarnComparisonLine::query()->create(collect($l)->only([...YarnProcurement::YARN, 'buyer_id', 'supplier_id'])->all() + [
                'company_id' => $row->company_id, 'comparison_id' => $row->id, 'sl_no' => (int) $l['sl_no'], 'uom' => UomCodes::normalize($l['uom']),
                'qty_local' => (float) ($l['qty_local'] ?? 0), 'qty_import' => (float) ($l['qty_import'] ?? 0),
                'present_stock' => (float) ($l['present_stock'] ?? 0), 'pipeline' => (float) ($l['pipeline'] ?? 0), 'booking_price' => $l['booking_price'] ?? null,
            ]);
        }
        foreach ($data['offers'] as $o) {
            YarnOffer::query()->create(collect($o)->only(['sl_no', 'source', 'supplier_id', 'price', 'currency_id', 'conf_qty', 'lead_days', 'remarks'])->all() + [
                'company_id' => $row->company_id, 'comparison_id' => $row->id,
            ]);
        }
    }

    /** Qty in Kg: what is worth ordering — the total less present stock and the open pipeline, never below nothing. */
    public static function worthOrdering(float $total, float $stock, float $pipeline): float
    {
        return round(max(0.0, $total - $stock - $pipeline), 4);
    }

    /** @return array<string, mixed> */
    public static function presentComparison(YarnComparison $row): array
    {
        $names = YarnProcurement::names();
        $req = YarnRequisition::query()->find($row->requisition_id);
        $lines = YarnComparisonLine::query()->where('comparison_id', $row->id)->orderBy('sl_no')->get();
        $offers = YarnOffer::query()->where('comparison_id', $row->id)->orderBy('sl_no')->orderBy('price')->get();

        return [
            'cs_no' => $row->cs_no, 'cs_date' => $row->cs_date->toDateString(), 'requisition_no' => $req?->requisition_no, 'requisition_date' => $row->requisition_date?->toDateString(),
            'remarks' => $row->remarks, 'status' => $row->status, 'editable' => $row->status === 'DRAFT',
            'lines' => $lines->map(function (YarnComparisonLine $l) use ($names) {
                $total = round((float) $l->qty_local + (float) $l->qty_import, 4);

                return collect($l->only([...YarnProcurement::YARN, 'buyer_id', 'supplier_id']))->all() + [
                    'sl_no' => (int) $l->sl_no, 'composition' => $names['compositions'][$l->composition_id] ?? null, 'count' => $names['counts'][$l->yarn_count_id] ?? null,
                    'knit_start_date' => $l->knit_start_date?->toDateString(), 'ship_date' => $l->ship_date?->toDateString(),
                    'qty_local' => (float) $l->qty_local, 'qty_import' => (float) $l->qty_import, 'total' => $total,
                    'present_stock' => (float) $l->present_stock, 'pipeline' => (float) $l->pipeline,
                    'qty_kg' => self::worthOrdering($total, (float) $l->present_stock, (float) $l->pipeline),
                    'booking_price' => $l->booking_price !== null ? (float) $l->booking_price : null,
                ];
            })->values()->all(),
            'offers' => $offers->map(fn (YarnOffer $o) => [
                'sl_no' => (int) $o->sl_no, 'source' => $o->source, 'supplier_id' => $o->supplier_id, 'price' => (float) $o->price, 'currency_id' => $o->currency_id,
                'conf_qty' => $o->conf_qty !== null ? (float) $o->conf_qty : null, 'lead_days' => $o->lead_days, 'remarks' => $o->remarks,
            ])->values()->all(),
        ];
    }
}
