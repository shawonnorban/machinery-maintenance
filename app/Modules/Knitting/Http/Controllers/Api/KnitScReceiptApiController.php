<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitScReceipt;
use App\Modules\Knitting\Models\KnitScReceiptFabric;
use App\Modules\Knitting\Models\KnitScReceiptYarn;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Production (AnnotechERP 15/04) → Sub-Contract Programme
 * Receipt, as Brickland works it: a knitting programme a party places with
 * this mill (SCR-…, or the party's own reference when Auto? is unticked).
 * Colour-wise fabric lines, each a serial (Fab. Ord. SL — blank takes the
 * next free one), and the yarn the party's store issues against a serial.
 * Saving writes a draft; Confirm fixes the lines so the floor may plan
 * against them; Start Production, Close and Cancel follow from the register.
 */
class KnitScReceiptApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->allow('knitting.production.view');
        $f = $request->validate([
            'party' => ['nullable', 'string'], 'store' => ['nullable', 'string'], 'status' => ['nullable', Rule::in(KnitScReceipt::STATUSES)],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);
        $rows = KnitScReceipt::query()
            ->when(! empty($f['party']), fn ($q) => $q->where('party_id', $f['party']))
            ->when(! empty($f['store']), fn ($q) => $q->where('yarn_store_id', $f['store']))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('prog_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('prog_date', '<=', $f['to']))
            ->orderByDesc('prog_date')->orderByDesc('program_no')->limit(300)->get();
        $ids = $rows->pluck('id');
        $fab = KnitScReceiptFabric::query()->whereIn('receipt_id', $ids)->groupBy('receipt_id')->selectRaw('receipt_id, count(*) as n, sum(qty) as q')->get()->keyBy('receipt_id');
        $yarn = KnitScReceiptYarn::query()->whereIn('receipt_id', $ids)->groupBy('receipt_id')->selectRaw('receipt_id, sum(required_qty) as q')->pluck('q', 'receipt_id');
        $parties = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('party_id')->unique())->pluck('name', 'id');
        $stores = DB::table('stores')->whereIn('id', $rows->pluck('yarn_store_id')->unique())->pluck('name', 'id');
        $company = DB::table('companies')->where('id', app(TenantContext::class)->companyId())->value('name');

        return ApiResponse::ok($rows->map(fn (KnitScReceipt $r) => [
            'program_no' => $r->program_no, 'prog_date' => $r->prog_date->toDateString(), 'party' => $parties[$r->party_id] ?? null,
            'yarn_store' => $stores[$r->yarn_store_id] ?? null, 'company' => $company, 'fabrics' => (int) ($fab[$r->id]->n ?? 0),
            'qty' => round((float) ($fab[$r->id]->q ?? 0), 3), 'yarn_qty' => round((float) ($yarn[$r->id] ?? 0), 3), 'remarks' => $r->remarks, 'status' => $r->status,
        ])->values()->all());
    }

    /** The yarn a store holds, lot by lot — what the yarn rows pick Yarn and Brand from. */
    public function storeYarns(Request $request): JsonResponse
    {
        $this->allow('knitting.production.view');
        $store = $request->validate(['store' => ['required', 'string']])['store'];

        return ApiResponse::ok(YarnStore::lots($store, false));
    }

    public function show(string $no): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->present($this->find($no)));
    }

    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $no ? $this->find($no) : null;
        if ($row && $row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('knitting.sc.v.receipt_fixed', ['no' => $row->program_no, 'status' => __("knitting.sc.receipt_status.{$row->status}")])]);
        }
        $company = app(TenantContext::class)->companyId();
        $auto = $request->boolean('auto_number', true);
        $data = $request->validate([
            'auto_number' => ['boolean'],
            'program_no' => [Rule::requiredIf(! $auto), 'nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9 _.\-]*$/',
                Rule::unique('knit_sc_receipts', 'program_no')->where('company_id', $company)->ignore($row?->id)],
            'prog_date' => ['required', 'date'],
            'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'yarn_store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'fabrics' => ['present', 'array', 'max:200'],
            'fabrics.*.sl' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'fabrics.*.order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'fabrics.*.party_ref' => ['nullable', 'string', 'max:64'],
            'fabrics.*.fabric_type_id' => ['nullable', Rule::exists('fabric_types', 'id')->where('company_id', $company)],
            'fabrics.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'fabrics.*.colour_name' => ['nullable', 'string', 'max:80'],
            'fabrics.*.composition_id' => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'fabrics.*.gsm' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'fabrics.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'fabrics.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)],
            'fabrics.*.finish_dia' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'fabrics.*.dia_unit' => ['nullable', Rule::in(['INCH', 'CM'])],
            'fabrics.*.width_type' => ['nullable', Rule::in(['OPEN', 'TUBE'])],
            'fabrics.*.yd_type' => ['nullable', Rule::in(KnitScReceipt::YD_TYPES)],
            'fabrics.*.feeder' => ['nullable', 'integer', 'min:0', 'max:999'],
            'fabrics.*.mc_dia_id' => ['nullable', Rule::exists('knit_ref_entries', 'id')->where('company_id', $company)->where('list', 'DIA')],
            'fabrics.*.gauge_id' => ['nullable', Rule::exists('knit_ref_entries', 'id')->where('company_id', $company)->where('list', 'GG')],
            'fabrics.*.rate_type' => ['nullable', Rule::in(KnitScReceipt::RATE_TYPES)],
            'fabrics.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'fabrics.*.start_date' => ['nullable', 'date'],
            'fabrics.*.end_date' => ['nullable', 'date'],
            'fabrics.*.remarks' => ['nullable', 'string', 'max:300'],
            'yarns' => ['present', 'array', 'max:400'],
            'yarns.*.sl' => ['required', 'integer', 'min:1', 'max:9999'],
            'yarns.*.composition_id' => ['required', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'yarns.*.yarn_count_id' => ['nullable', Rule::exists('knit_yarn_counts', 'id')->where('company_id', $company)],
            'yarns.*.fab_part' => ['nullable', 'string', 'max:60'],
            'yarns.*.colour_group' => ['nullable', Rule::in(YarnProcurement::COLOURS)],
            'yarns.*.brand' => ['nullable', 'string', 'max:80'],
            'yarns.*.lot_no' => ['nullable', 'string', 'max:40'],
            'yarns.*.stitch_length' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'yarns.*.elastane_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'yarns.*.ratio_qty' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'yarns.*.required_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'yarns.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'program_no.required' => __('knitting.sc.v.party_no'), 'program_no.regex' => __('knitting.sc.v.party_no_format'), 'program_no.unique' => __('knitting.sc.v.party_no_taken'),
            'prog_date.required' => __('knitting.sc.v.prog_date'), 'party_id.required' => __('knitting.sc.v.party'), 'yarn_store_id.required' => __('knitting.sc.v.store'),
            'fabrics.*.qty.required' => __('knitting.sc.v.qty'), 'fabrics.*.qty.gt' => __('knitting.sc.v.qty'), 'fabrics.*.uom.required' => __('knitting.sc.v.uom'),
            'yarns.*.sl.required' => __('knitting.sc.v.yarn_sl'), 'yarns.*.composition_id.required' => __('knitting.sc.v.yarn'),
        ]);
        $fabrics = YarnStore::inOrder($data['fabrics']);
        if ($fabrics === []) {
            throw ValidationException::withMessages(['fabrics' => __('knitting.sc.v.no_fabrics')]);
        }
        // A blank Fab. Ord. SL takes the next free serial on this programme.
        $next = (int) collect($fabrics)->max(fn ($f) => (int) ($f['sl'] ?? 0));
        foreach ($fabrics as $i => $f) {
            if (! empty($f['end_date']) && ! empty($f['start_date']) && $f['end_date'] < $f['start_date']) {
                throw ValidationException::withMessages(["fabrics.{$i}.end_date" => __('knitting.sc.v.end_before', ['row' => $i + 1])]);
            }
            if (empty($f['sl'])) {
                $fabrics[$i]['sl'] = ++$next;
            }
        }
        $serials = array_map(fn ($f) => (int) $f['sl'], $fabrics);
        foreach (array_diff_assoc($serials, array_unique($serials)) as $i => $sl) {
            throw ValidationException::withMessages(["fabrics.{$i}.sl" => __('knitting.sc.v.sl_twice', ['sl' => $sl])]);
        }
        $yarns = YarnStore::inOrder($data['yarns']);
        foreach ($yarns as $i => $y) {
            if (! in_array((int) $y['sl'], $serials, true)) {
                throw ValidationException::withMessages(["yarns.{$i}.sl" => __('knitting.sc.v.yarn_sl_unknown', ['sl' => $y['sl'], 'row' => $i + 1])]);
            }
        }
        $row = DB::transaction(function () use ($row, $data, $auto, $fabrics, $yarns): KnitScReceipt {
            $head = [
                'prog_date' => $data['prog_date'], 'party_id' => $data['party_id'], 'yarn_store_id' => $data['yarn_store_id'], 'remarks' => $data['remarks'] ?? null,
                'auto_number' => $auto,
            ];
            if (! $auto) {
                $head['program_no'] = trim($data['program_no']);
            } elseif (! $row || ! $row->auto_number) {
                $head['program_no'] = KnitProduction::nextNumber('SCR', 'knit_sc_receipts', 'program_no', $data['prog_date']);
            }
            $row ??= KnitScReceipt::query()->create($head + ['status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitScReceiptFabric::query()->where('receipt_id', $row->id)->delete();
            KnitScReceiptYarn::query()->where('receipt_id', $row->id)->delete();
            foreach ($fabrics as $f) {
                KnitScReceiptFabric::query()->create(['company_id' => $row->company_id, 'receipt_id' => $row->id, 'uom' => UomCodes::normalize($f['uom'])]
                    + collect($f)->only(['sl', 'order_id', 'party_ref', 'fabric_type_id', 'colour_id', 'colour_name', 'composition_id', 'gsm', 'qty', 'finish_dia', 'dia_unit',
                        'width_type', 'yd_type', 'feeder', 'mc_dia_id', 'gauge_id', 'rate_type', 'rate', 'start_date', 'end_date', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all());
            }
            foreach ($yarns as $i => $y) {
                KnitScReceiptYarn::query()->create(['company_id' => $row->company_id, 'receipt_id' => $row->id, 'sort_order' => $i + 1]
                    + collect($y)->only(['sl', 'composition_id', 'yarn_count_id', 'fab_part', 'colour_group', 'brand', 'lot_no', 'stitch_length', 'elastane_pct',
                        'ratio_qty', 'required_qty', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all());
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->present($row->refresh())) : ApiResponse::created($this->present($row->refresh()));
    }

    /** Confirm, Start Production, Close, Cancel — from the register. */
    public function transition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->find($no);
        [$from, $to] = match ($action) {
            'confirm' => [['DRAFT'], 'CONFIRMED'],
            'start' => [['CONFIRMED'], 'IN_PRODUCTION'],
            'close' => [['CONFIRMED', 'IN_PRODUCTION'], 'CLOSED'],
            'cancel' => [['DRAFT', 'CONFIRMED', 'IN_PRODUCTION'], 'CANCELLED'],
        };
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.sc.v.cannot', ['no' => $row->program_no, 'status' => __("knitting.sc.receipt_status.{$row->status}"), 'action' => __("knitting.sc.receipt_action.{$action}")])]);
        }
        $row->update(['status' => $to]);

        return ApiResponse::ok($this->present($row->refresh()) + ['message' => __("knitting.sc.receipt_done.{$action}", ['no' => $row->program_no])]);
    }

    private function find(string $no): KnitScReceipt
    {
        return KnitScReceipt::query()->where('program_no', $no)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function present(KnitScReceipt $r): array
    {
        $company = $r->company_id;
        $fabrics = KnitScReceiptFabric::query()->where('receipt_id', $r->id)->orderBy('sl')->get();
        $yarns = KnitScReceiptYarn::query()->where('receipt_id', $r->id)->orderBy('sort_order')->get();
        $party = DB::table('mp_suppliers')->where('id', $r->party_id)->first(['name', 'address']);
        $orders = Order::query()->whereIn('id', $fabrics->pluck('order_id')->filter()->unique())->get()->keyBy('id');
        $types = DB::table('fabric_types')->whereIn('id', $fabrics->pluck('fabric_type_id')->filter())->pluck('name', 'id');
        $colours = DB::table('colours')->whereIn('id', $fabrics->pluck('colour_id')->filter())->get(['id', 'code', 'name'])->mapWithKeys(fn ($c) => [$c->id => $c->code ? "{$c->name} ({$c->code})" : $c->name]);
        $comps = DB::table('fiber_compositions')->where('company_id', $company)->pluck('description', 'id');
        $counts = DB::table('knit_yarn_counts')->where('company_id', $company)->pluck('description', 'id');
        $refs = DB::table('knit_ref_entries')->where('company_id', $company)->pluck('name', 'id');

        return [
            'id' => $r->id, 'program_no' => $r->program_no, 'auto_number' => $r->auto_number, 'prog_date' => $r->prog_date->toDateString(), 'status' => $r->status,
            'party_id' => $r->party_id, 'party' => $party?->name, 'party_address' => $party?->address, 'yarn_store_id' => $r->yarn_store_id,
            'yarn_store' => DB::table('stores')->where('id', $r->yarn_store_id)->value('name'), 'company' => DB::table('companies')->where('id', $company)->value('name'),
            'remarks' => $r->remarks, 'qty' => round((float) $fabrics->sum('qty'), 3), 'yarn_qty' => round((float) $yarns->sum('required_qty'), 3),
            'fabrics' => $fabrics->map(fn (KnitScReceiptFabric $f) => collect($f->only(['sl', 'order_id', 'party_ref', 'fabric_type_id', 'colour_id', 'colour_name', 'composition_id', 'gsm', 'qty',
                'uom', 'finish_dia', 'dia_unit', 'width_type', 'yd_type', 'feeder', 'mc_dia_id', 'gauge_id', 'rate_type', 'rate', 'remarks']))->all() + [
                    'start_date' => $f->start_date?->toDateString(), 'end_date' => $f->end_date?->toDateString(),
                    'order' => KnitProduction::orderLabel($orders[$f->order_id] ?? null), 'fabric_type' => $types[$f->fabric_type_id] ?? null,
                    'colour' => $colours[$f->colour_id] ?? null, 'composition' => $comps[$f->composition_id] ?? null,
                    'mc_dia' => $refs[$f->mc_dia_id] ?? null, 'gauge' => $refs[$f->gauge_id] ?? null,
                ])->values()->all(),
            'yarns' => $yarns->map(fn (KnitScReceiptYarn $y) => collect($y->only(['sl', 'composition_id', 'yarn_count_id', 'fab_part', 'colour_group', 'brand', 'lot_no', 'stitch_length',
                'elastane_pct', 'ratio_qty', 'required_qty', 'remarks']))->all() + [
                    'yarn' => trim(($comps[$y->composition_id] ?? '').' '.($counts[$y->yarn_count_id] ?? '')),
                ])->values()->all(),
        ];
    }
}
