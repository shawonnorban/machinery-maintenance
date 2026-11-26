<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitYarnTest;
use App\Modules\Knitting\Models\KnitYarnTestOrder;
use App\Modules\Knitting\Models\KnitYarnTestOrderLine;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\Knitting\Support\YarnTesting;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Production (AnnotechERP 15/04) → Knit Plan & Yarn Test
 * Indent, as Brickland works it: the lab's own requisition (YTO-…) — which
 * lots are to be tested, who asked for them and who is to run the test, one
 * line per lot. Saving raises it as Waiting for Issue; the store issues
 * against it from the register; the lab files what it measured on the
 * Yarn Test screen, which marks it Tested. A Tested order is no longer
 * rewritten or cancelled.
 */
class KnitYarnTestOrderApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok(YarnTesting::options());
    }

    public function index(): JsonResponse
    {
        $this->allow('knitting.production.view');
        $rows = KnitYarnTestOrder::query()->orderByDesc('order_date')->orderByDesc('order_no')->limit(500)->get();

        return ApiResponse::ok($this->register($rows));
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
        if ($row && ! in_array($row->status, ['WAITING_ISSUE', 'ISSUED'], true)) {
            throw ValidationException::withMessages(['status' => __('knitting.yt.v.order_fixed', ['no' => $row->order_no, 'status' => __("knitting.yt.status.{$row->status}")])]);
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'order_date' => ['required', 'date'], 'req_type' => ['nullable', Rule::in(YarnStore::REQ_TYPES)],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)],
            'requested_by' => ['nullable', Rule::exists('hr_employees', 'id')->where('company_id', $company)],
            'attn_to' => ['nullable', Rule::exists('hr_employees', 'id')->where('company_id', $company)],
            'yarn_description' => ['required', 'string', 'max:200'], 'knit_card_no' => ['nullable', 'string', 'max:40'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'],
            'lines.*.composition_id' => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'lines.*.yarn_count_id' => ['nullable', Rule::exists('knit_yarn_counts', 'id')->where('company_id', $company)],
            'lines.*.colour_group' => ['nullable', Rule::in(YarnProcurement::COLOURS)],
            'lines.*.brand' => ['nullable', 'string', 'max:80'],
            'lines.*.supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'lines.*.lc_no' => ['nullable', 'string', 'max:40'], 'lines.*.lot_no' => ['required', 'string', 'max:40'],
            'lines.*.qty' => ['nullable', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'order_date.required' => __('knitting.yt.v.order_date'), 'yarn_description.required' => __('knitting.yt.v.yarn_description'),
            'lines.*.lot_no.required' => __('knitting.yt.v.lot'), 'lines.*.qty.gt' => __('knitting.yt.v.qty'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.yt.v.no_lots')]);
        }
        $row = DB::transaction(function () use ($row, $data, $lines): KnitYarnTestOrder {
            $head = ['order_date' => $data['order_date'], 'req_type' => $data['req_type'] ?? 'YARN_TEST'] + collect($data)->only(['store_id', 'requested_by', 'attn_to', 'yarn_description', 'knit_card_no', 'remarks'])
                ->map(fn ($v) => $v === '' ? null : $v)->all() + ['store_id' => null, 'requested_by' => null, 'attn_to' => null, 'knit_card_no' => null, 'remarks' => null];
            $row ??= KnitYarnTestOrder::query()->create($head + [
                'order_no' => KnitProduction::nextNumber('YTO', 'knit_yarn_test_orders', 'order_no', $data['order_date']), 'status' => 'WAITING_ISSUE',
                'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitYarnTestOrderLine::query()->where('order_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitYarnTestOrderLine::query()->create(['company_id' => $row->company_id, 'order_id' => $row->id, 'sort_order' => $i + 1, 'lot_no' => trim($l['lot_no']),
                    'uom' => empty($l['uom']) ? null : UomCodes::normalize($l['uom'])]
                    + collect($l)->only(['composition_id', 'yarn_count_id', 'colour_group', 'brand', 'supplier_id', 'lc_no', 'qty', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all());
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->present($row->refresh())) : ApiResponse::created($this->present($row->refresh()));
    }

    /** Issue (the store has sent the lots to the lab) or Cancel, from the register. */
    public function transition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->find($no);
        [$from, $to] = match ($action) {
            'issue' => [['WAITING_ISSUE'], 'ISSUED'],
            'cancel' => [['WAITING_ISSUE', 'ISSUED'], 'CANCELLED'],
        };
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.yt.v.cannot', ['no' => $row->order_no, 'status' => __("knitting.yt.status.{$row->status}"), 'action' => __("knitting.yt.action.{$action}")])]);
        }
        $row->update(['status' => $to] + ($action === 'issue' ? ['issued_at' => now()] : []));

        return ApiResponse::ok($this->register(collect([$row->refresh()]))[0] + ['message' => __("knitting.yt.done.{$action}", ['no' => $row->order_no])]);
    }

    private function find(string $no): KnitYarnTestOrder
    {
        return KnitYarnTestOrder::query()->where('order_no', $no)->firstOrFail();
    }

    /** @return list<array<string, mixed>> */
    private function register($rows): array
    {
        $people = DB::table('hr_employees')->whereIn('id', $rows->pluck('requested_by')->merge($rows->pluck('attn_to'))->filter()->unique())->pluck('name', 'id');
        $lots = KnitYarnTestOrderLine::query()->whereIn('order_id', $rows->pluck('id'))->orderBy('sort_order')->get()->groupBy('order_id');

        return $rows->map(fn (KnitYarnTestOrder $o) => [
            'order_no' => $o->order_no, 'order_date' => $o->order_date->toDateString(), 'requested_by_id' => $o->requested_by, 'attn_to_id' => $o->attn_to,
            'requested_by' => $people[$o->requested_by] ?? null, 'attn_to' => $people[$o->attn_to] ?? null, 'yarn_description' => $o->yarn_description,
            'knit_card_no' => $o->knit_card_no, 'lots' => ($lots[$o->id] ?? collect())->pluck('lot_no')->implode(', '), 'results' => YarnTesting::summary($o), 'status' => $o->status,
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(KnitYarnTestOrder $o): array
    {
        $names = YarnProcurement::names();
        $people = DB::table('hr_employees')->whereIn('id', array_filter([$o->requested_by, $o->attn_to]))->pluck('name', 'id');
        $lines = KnitYarnTestOrderLine::query()->where('order_id', $o->id)->orderBy('sort_order')->get();
        $suppliers = DB::table('mp_suppliers')->whereIn('id', $lines->pluck('supplier_id')->filter())->pluck('name', 'id');
        $answers = YarnTesting::answers($lines->map(fn ($l) => $l->only(['lot_no', 'composition_id', 'yarn_count_id'])));
        $store = $o->store_id ? DB::table('stores')->where('id', $o->store_id)->first(['name', 'code']) : null;

        return [
            'id' => $o->id, 'order_no' => $o->order_no, 'order_date' => $o->order_date->toDateString(), 'req_type' => $o->req_type, 'status' => $o->status,
            'store_id' => $o->store_id, 'store' => $store ? "{$store->name} ({$store->code})" : null,
            'requested_by' => $o->requested_by, 'requested_by_name' => $people[$o->requested_by] ?? null, 'attn_to' => $o->attn_to, 'attn_to_name' => $people[$o->attn_to] ?? null,
            'yarn_description' => $o->yarn_description, 'knit_card_no' => $o->knit_card_no, 'remarks' => $o->remarks,
            'company' => DB::table('companies')->where('id', $o->company_id)->value('name'),
            'test_no' => KnitYarnTest::query()->where('order_id', $o->id)->value('test_no'),
            'lines' => $lines->map(function (KnitYarnTestOrderLine $l) use ($names, $suppliers, $answers) {
                $answer = YarnTesting::answerFor($answers, $l->lot_no, $l->composition_id, $l->yarn_count_id);

                return collect($l->only(['composition_id', 'yarn_count_id', 'colour_group', 'brand', 'supplier_id', 'lc_no', 'lot_no', 'qty', 'uom', 'remarks']))->all() + [
                    'yarn' => trim(($names['compositions'][$l->composition_id] ?? '').' '.($names['counts'][$l->yarn_count_id] ?? '')) ?: null,
                    'supplier' => $suppliers[$l->supplier_id] ?? null, 'result' => $answer?->result,
                ];
            })->values()->all(),
        ];
    }
}
