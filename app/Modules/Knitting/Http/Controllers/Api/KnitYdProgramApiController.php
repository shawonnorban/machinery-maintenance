<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitYdLossLine;
use App\Modules\Knitting\Models\KnitYdProgram;
use App\Modules\Knitting\Models\KnitYdProgramLine;
use App\Modules\Knitting\Models\KnitYdReceiptLine;
use App\Modules\Knitting\Models\KnitYdRevision;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnDyeing;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
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
 * Knit Production → Production (AnnotechERP 15/04) → Yarn Dyeing Programme
 * Register: the Y/D programmes (YDP-…) with what the store has issued
 * against each, what has come back dyed and what is still with the party;
 * the programme form itself (a dye house, a buyer's order, the shades to
 * come back); and Y/D Program Revisions, numbered in order, each with why.
 * Also the Knitting Y/D Statement (15/03): the same figures with the process
 * loss beside them.
 */
class KnitYdProgramApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allowEither('knitting.production.view', 'knitting.ystore.view');

        return ApiResponse::ok(YarnDyeing::options() + ['sc_programs' => YarnDyeing::scPrograms()]);
    }

    public function index(): JsonResponse
    {
        $this->allowEither('knitting.production.view', 'knitting.ystore.view');
        $rows = KnitYdProgram::query()->orderByDesc('program_date')->orderByDesc('program_no')->limit(500)->get();

        return ApiResponse::ok($this->rows($rows));
    }

    public function show(string $no): JsonResponse
    {
        $this->allowEither('knitting.production.view', 'knitting.ystore.view');

        return ApiResponse::ok($this->present($this->program($no)));
    }

    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $no ? $this->program($no) : null;
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'program_date' => ['required', 'date'], 'yd_type' => ['required', Rule::in(KnitYdProgram::TYPES)],
            'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:program_date'],
            'status' => ['required', Rule::in(KnitYdProgram::STATUSES)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'],
            'lines.*.id' => ['nullable', 'string'],
            'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'lines.*.colour_name' => ['required', 'string', 'max:80'], 'lines.*.colour_group' => ['nullable', Rule::in(YarnProcurement::COLOURS)],
            'lines.*.composition_id' => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'lines.*.yarn_count_id' => ['nullable', Rule::exists('knit_yarn_counts', 'id')->where('company_id', $company)],
            'lines.*.lot_no' => ['nullable', 'string', 'max:40'], 'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)], 'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'program_date.required' => __('knitting.yd.v.program_date'), 'yd_type.required' => __('knitting.yd.v.type'), 'supplier_id.required' => __('knitting.yd.v.party'),
            'status.required' => __('knitting.yd.v.status'), 'lines.*.colour_name.required' => __('knitting.yd.v.colour'),
            'lines.*.qty.required' => __('knitting.yd.v.qty'), 'lines.*.qty.gt' => __('knitting.yd.v.qty'), 'lines.*.uom.required' => __('knitting.yd.v.uom'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.yd.v.no_lines')]);
        }
        if (! empty($data['order_id'])) {
            $buyer = Order::query()->whereKey($data['order_id'])->value('buyer_id');
            if (! empty($data['buyer_id']) && $buyer && $buyer !== $data['buyer_id']) {
                throw ValidationException::withMessages(['order_id' => __('knitting.yd.v.order_buyer')]);
            }
            $data['buyer_id'] = ($data['buyer_id'] ?? null) ?: $buyer;
        }
        $kept = collect($lines)->pluck('id')->filter()->all();
        if ($row) {
            $gone = KnitYdProgramLine::query()->where('program_id', $row->id)->whereNotIn('id', $kept)->get();
            foreach ($gone as $g) {
                if (KnitYdReceiptLine::query()->where('program_line_id', $g->id)->exists() || KnitYdLossLine::query()->where('program_line_id', $g->id)->exists()) {
                    throw ValidationException::withMessages(['lines' => __('knitting.yd.v.shade_used', ['shade' => $g->colour_name])]);
                }
            }
        }
        $row = DB::transaction(function () use ($row, $data, $lines, $kept): KnitYdProgram {
            $head = collect($data)->only(['program_date', 'yd_type', 'supplier_id', 'buyer_id', 'order_id', 'delivery_date', 'status', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['buyer_id' => null, 'order_id' => null, 'delivery_date' => null, 'remarks' => null];
            $row ??= KnitYdProgram::query()->create($head + [
                'program_no' => KnitProduction::nextNumber('YDP', 'knit_yd_programs', 'program_no', $data['program_date']), 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitYdProgramLine::query()->where('program_id', $row->id)->whereNotIn('id', $kept)->delete();
            foreach ($lines as $i => $l) {
                $values = ['sort_order' => $i + 1, 'qty' => (float) $l['qty'], 'uom' => UomCodes::normalize($l['uom'])]
                    + collect($l)->only(['colour_id', 'colour_name', 'colour_group', 'composition_id', 'yarn_count_id', 'lot_no', 'rate', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all()
                    + ['colour_id' => null, 'colour_group' => null, 'composition_id' => null, 'yarn_count_id' => null, 'lot_no' => null, 'rate' => null, 'remarks' => null];
                $line = ! empty($l['id']) ? KnitYdProgramLine::query()->where('program_id', $row->id)->whereKey($l['id'])->first() : null;
                $line ? $line->update($values) : KnitYdProgramLine::query()->create($values + ['company_id' => $row->company_id, 'program_id' => $row->id]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->present($row->refresh())) : ApiResponse::created($this->present($row->refresh()));
    }

    /* ---------------- Y/D Program Revisions ---------------- */

    public function revise(Request $request): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $data = $request->validate([
            'program_no' => ['required', 'string'], 'revised_on' => ['required', 'date'], 'reason' => ['required', 'string', 'max:500'],
        ], [
            'program_no.required' => __('knitting.yd.v.program'), 'revised_on.required' => __('knitting.yd.v.revised_on'), 'reason.required' => __('knitting.yd.v.reason'),
        ]);
        $row = KnitYdProgram::query()->where('program_no', $data['program_no'])->first()
            ?? throw ValidationException::withMessages(['program_no' => __('knitting.yd.v.program')]);
        if ($data['revised_on'] < $row->program_date->toDateString()) {
            throw ValidationException::withMessages(['revised_on' => __('knitting.yd.v.revised_before', ['date' => $row->program_date->format('d M Y')])]);
        }
        DB::transaction(function () use ($row, $data): void {
            $locked = KnitYdProgram::query()->whereKey($row->id)->lockForUpdate()->first();
            $next = $locked->rev_no + 1;
            KnitYdRevision::query()->create([
                'company_id' => $locked->company_id, 'program_id' => $locked->id, 'rev_no' => $next, 'revised_on' => $data['revised_on'],
                'reason' => $data['reason'], 'created_by' => $this->caller()->auditUserId(),
            ]);
            $locked->update(['rev_no' => $next]);
        });

        return ApiResponse::created($this->present($row->refresh()));
    }

    /* ---------------- Knitting Y/D Statement ---------------- */

    public function statement(Request $request): JsonResponse
    {
        $this->allowEither('knitting.ystore.view', 'knitting.production.view');
        $f = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'], 'buyer' => ['nullable', 'string'], 'order' => ['nullable', 'string'], 'program' => ['nullable', 'string'],
            'party' => ['nullable', 'string'], 'status' => ['nullable', Rule::in(KnitYdProgram::STATUSES)],
        ]);
        $orders = ! empty($f['month'])
            ? Order::query()->whereNotNull('ship_date')->whereRaw("to_char(ship_date, 'YYYY-MM') = ?", [$f['month']])->pluck('id')
            : null;
        $rows = KnitYdProgram::query()
            ->when(! empty($f['buyer']), fn ($q) => $q->where('buyer_id', $f['buyer']))
            ->when(! empty($f['order']), fn ($q) => $q->where('order_id', $f['order']))
            ->when(! empty($f['program']), fn ($q) => $q->where('program_no', 'ilike', '%'.$f['program'].'%'))
            ->when(! empty($f['party']), fn ($q) => $q->where('supplier_id', $f['party']))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when($orders !== null, fn ($q) => $q->whereIn('order_id', $orders))
            ->orderByDesc('program_date')->orderByDesc('program_no')->limit(500)->get();

        return ApiResponse::ok($this->rows($rows));
    }

    /* ---------------- helpers ---------------- */

    private function program(string $no): KnitYdProgram
    {
        return KnitYdProgram::query()->where('program_no', $no)->firstOrFail();
    }

    /**
     * Register / statement rows.
     *
     * @param  Collection<int, KnitYdProgram>  $rows
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $rows): array
    {
        $figures = YarnDyeing::figures($rows);
        $suppliers = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('supplier_id')->unique())->pluck('name', 'id');
        $buyers = DB::table('buyers')->whereIn('id', $rows->pluck('buyer_id')->filter()->unique())->get(['id', 'name', 'short_name'])->keyBy('id');
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id')->filter()->unique())->get(['id', 'order_no', 'style_no', 'work_style_no', 'job_no', 'ship_date'])->keyBy('id');

        return $rows->map(function (KnitYdProgram $p) use ($figures, $suppliers, $buyers, $orders) {
            $o = $orders[$p->order_id] ?? null;
            $b = $buyers[$p->buyer_id] ?? null;

            return [
                'program_no' => $p->program_no, 'yd_type' => $p->yd_type, 'rev_no' => $p->rev_no, 'program_date' => $p->program_date->toDateString(),
                'delivery_date' => $p->delivery_date?->toDateString(), 'buyer_id' => $p->buyer_id, 'buyer' => $b ? ($b->short_name ?: $b->name) : null,
                'order_id' => $p->order_id, 'order_no' => $o?->order_no, 'style' => $o ? ($o->work_style_no ?: $o->style_no ?: $o->job_no) : null,
                'ship_month' => $o?->ship_date?->format('Y-m'), 'supplier_id' => $p->supplier_id, 'supplier' => $suppliers[$p->supplier_id] ?? null, 'status' => $p->status,
            ] + $figures['programs'][$p->id];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(KnitYdProgram $p): array
    {
        $names = YarnProcurement::names();
        $figures = YarnDyeing::figures(collect([$p]));
        $party = DB::table('mp_suppliers')->where('id', $p->supplier_id)->first(['name', 'address']);
        $order = $p->order_id ? Order::query()->whereKey($p->order_id)->first(['order_no', 'style_no', 'work_style_no']) : null;
        $users = DB::table('users')->pluck('name', 'id');

        return [
            'id' => $p->id, 'program_no' => $p->program_no, 'program_date' => $p->program_date->toDateString(), 'yd_type' => $p->yd_type,
            'supplier_id' => $p->supplier_id, 'supplier' => $party?->name, 'supplier_address' => $party?->address,
            'buyer_id' => $p->buyer_id, 'buyer' => $p->buyer_id ? DB::table('buyers')->where('id', $p->buyer_id)->value('name') : null,
            'order_id' => $p->order_id, 'order' => $order ? trim(($order->work_style_no ?: $order->style_no).' · '.$order->order_no, ' ·') : null,
            'delivery_date' => $p->delivery_date?->toDateString(), 'rev_no' => $p->rev_no, 'status' => $p->status, 'remarks' => $p->remarks,
            'company' => DB::table('companies')->where('id', $p->company_id)->value('name'),
            'used' => KnitYdReceiptLine::query()->whereIn('program_line_id', KnitYdProgramLine::query()->where('program_id', $p->id)->pluck('id'))->exists(),
        ] + $figures['programs'][$p->id] + [
            'lines' => KnitYdProgramLine::query()->where('program_id', $p->id)->orderBy('sort_order')->get()->map(fn (KnitYdProgramLine $l) => collect($l->only([
                'id', 'colour_id', 'colour_name', 'colour_group', 'composition_id', 'yarn_count_id', 'lot_no', 'qty', 'uom', 'rate', 'remarks',
            ]))->all() + ['yarn' => trim(($names['compositions'][$l->composition_id] ?? '').' '.($names['counts'][$l->yarn_count_id] ?? '')) ?: null] + $figures['lines'][$l->id])->values()->all(),
            'revisions' => KnitYdRevision::query()->where('program_id', $p->id)->orderByDesc('rev_no')->get()->map(fn (KnitYdRevision $r) => [
                'rev_no' => $r->rev_no, 'revised_on' => $r->revised_on->toDateString(), 'reason' => $r->reason, 'by' => $users[$r->created_by] ?? null,
                'at' => $r->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
