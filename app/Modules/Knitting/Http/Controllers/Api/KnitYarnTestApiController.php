<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Knitting\Models\KnitYarnTest;
use App\Modules\Knitting\Models\KnitYarnTestOrder;
use App\Modules\Knitting\Models\KnitYarnTestResult;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\Knitting\Support\YarnTesting;
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
 * Knit Production → Production (AnnotechERP 15/04) → Yarn Test Result
 * Register, Production Test Clearance and Approval Without Testing, as
 * Brickland works them:
 *
 * - A Yarn Test (YTS-…) files what the lab measured against a test order the
 *   store has issued: one register row per lot, Pending, Passed, Failed or
 *   Conditional, and the order is Tested. Filing again against the same
 *   order replaces what was there, so a corrected reading never leaves two
 *   answers about one lot.
 * - An approval without test releases lots the store is holding without a
 *   lab reading, on the Test By's authority; releasing a lot again replaces
 *   that release, which is the only way to correct one.
 * - The register reads both kinds of row (the Basis column says which).
 */
class KnitYarnTestApiController extends ApiController
{
    public function show(string $no): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->present(KnitYarnTest::query()->where('test_no', $no)->firstOrFail()));
    }

    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'order_no' => ['required', 'string'], 'test_date' => ['required', 'date'],
            'test_by' => ['required', Rule::exists('hr_employees', 'id')->where('company_id', $company)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'],
            'lines.*.composition_id' => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'lines.*.yarn_count_id' => ['nullable', Rule::exists('knit_yarn_counts', 'id')->where('company_id', $company)],
            'lines.*.colour_group' => ['nullable', Rule::in(YarnProcurement::COLOURS)],
            'lines.*.brand' => ['nullable', 'string', 'max:80'],
            'lines.*.supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'lines.*.lc_no' => ['nullable', 'string', 'max:40'], 'lines.*.lot_no' => ['required', 'string', 'max:40'],
            'lines.*.colour' => ['nullable', 'string', 'max:40'], 'lines.*.result' => ['required', Rule::in(KnitYarnTestResult::RESULTS)],
            'lines.*.note' => ['nullable', 'string', 'max:300'],
        ], [
            'order_no.required' => __('knitting.yt.v.req_no'), 'test_date.required' => __('knitting.yt.v.test_date'), 'test_by.required' => __('knitting.yt.v.test_by'),
            'lines.*.lot_no.required' => __('knitting.yt.v.lot'), 'lines.*.result.required' => __('knitting.yt.v.result'),
        ]);
        $order = KnitYarnTestOrder::query()->where('order_no', $data['order_no'])->first();
        if (! $order) {
            throw ValidationException::withMessages(['order_no' => __('knitting.yt.v.req_no')]);
        }
        if (! in_array($order->status, ['ISSUED', 'TESTED'], true)) {
            throw ValidationException::withMessages(['order_no' => __('knitting.yt.v.not_issued', ['no' => $order->order_no, 'status' => __("knitting.yt.status.{$order->status}")])]);
        }
        $test = KnitYarnTest::query()->where('order_id', $order->id)->first();
        if ($no && (! $test || $test->test_no !== $no)) {
            throw ValidationException::withMessages(['order_no' => __('knitting.yt.v.other_order', ['no' => $no])]);
        }
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.yt.v.no_test_lots')]);
        }
        $test = DB::transaction(function () use ($test, $order, $data, $lines): KnitYarnTest {
            $head = ['test_date' => $data['test_date'], 'test_by' => $data['test_by'], 'remarks' => $data['remarks'] ?? null];
            $test ??= KnitYarnTest::query()->create($head + [
                'order_id' => $order->id, 'test_no' => KnitProduction::nextNumber('YTS', 'knit_yarn_tests', 'test_no', $data['test_date']), 'created_by' => $this->caller()->auditUserId(),
            ]);
            $test->update($head);
            KnitYarnTestResult::query()->where('test_id', $test->id)->delete();
            foreach ($lines as $i => $l) {
                KnitYarnTestResult::query()->create([
                    'company_id' => $test->company_id, 'basis' => 'LAB', 'test_id' => $test->id, 'sort_order' => $i + 1, 'test_date' => $data['test_date'], 'test_by' => $data['test_by'],
                    'lot_no' => trim($l['lot_no']), 'result' => $l['result'], 'created_by' => $this->caller()->auditUserId(),
                ] + collect($l)->only(['composition_id', 'yarn_count_id', 'colour_group', 'brand', 'supplier_id', 'lc_no', 'colour', 'note'])->map(fn ($v) => $v === '' ? null : $v)->all());
            }
            $order->update(['status' => 'TESTED']);

            return $test;
        });
        $fresh = $test->wasRecentlyCreated;

        return $fresh ? ApiResponse::created($this->present($test->refresh())) : ApiResponse::ok($this->present($test->refresh()));
    }

    /** The register: `basis` LAB (Production Test Clearance), WAIVED (Approved Without Test) or both. */
    public function results(Request $request): JsonResponse
    {
        $this->allow('knitting.production.view');
        $f = $request->validate([
            'basis' => ['nullable', Rule::in(KnitYarnTestResult::BASES)], 'count' => ['nullable', 'string'], 'brand' => ['nullable', 'string', 'max:80'],
            'lot' => ['nullable', 'string', 'max:40'], 'status' => ['nullable', Rule::in(KnitYarnTestResult::RESULTS)], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);
        $rows = KnitYarnTestResult::query()
            ->when(! empty($f['basis']), fn ($q) => $q->where('basis', $f['basis']))
            ->when(! empty($f['count']), fn ($q) => $q->where('yarn_count_id', $f['count']))
            ->when(! empty($f['brand']), fn ($q) => $q->where('brand', $f['brand']))
            ->when(! empty($f['lot']), fn ($q) => $q->where('lot_no', 'ilike', '%'.$f['lot'].'%'))
            ->when(! empty($f['status']), fn ($q) => $q->where('result', $f['status']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('test_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('test_date', '<=', $f['to']))
            ->orderByDesc('test_date')->orderByDesc('created_at')->orderBy('sort_order')->limit(500)->get();

        return ApiResponse::ok($this->rows($rows));
    }

    /** Lots the store is holding, filtered, each with the answer it already carries. */
    public function waiverLots(Request $request): JsonResponse
    {
        $this->allow('knitting.production.view');
        $f = $request->validate(['brand' => ['nullable', 'string', 'max:80'], 'count' => ['nullable', 'string'], 'lot' => ['nullable', 'string', 'max:40']]);
        $lots = collect(YarnStore::lots(null, true))
            ->filter(fn ($l) => (empty($f['brand']) || $l['brand'] === $f['brand']) && (empty($f['count']) || $l['yarn_count_id'] === $f['count'])
                && (empty($f['lot']) || str_contains(mb_strtolower((string) $l['lot_no']), mb_strtolower($f['lot']))))
            ->values();
        $answers = YarnTesting::answers($lots);
        $stores = DB::table('stores')->whereIn('id', $lots->pluck('store_id')->unique())->pluck('name', 'id');
        $suppliers = DB::table('mp_suppliers')->whereIn('id', $lots->pluck('supplier_id')->filter()->unique())->pluck('name', 'id');

        return ApiResponse::ok($lots->map(function ($l) use ($answers, $stores, $suppliers) {
            $a = YarnTesting::answerFor($answers, $l['lot_no'], $l['composition_id'], $l['yarn_count_id']);

            return collect($l)->only(['lot_id', 'yarn', 'brand', 'lot_no', 'balance', 'uom'])->all() + [
                'store' => $stores[$l['store_id']] ?? null, 'supplier' => $suppliers[$l['supplier_id']] ?? null,
                'answered' => $a ? ['result' => $a->result, 'basis' => $a->basis, 'test_date' => $a->test_date->toDateString()] : null,
            ];
        })->all());
    }

    public function waive(Request $request): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'test_date' => ['required', 'date'], 'test_by' => ['required', Rule::exists('hr_employees', 'id')->where('company_id', $company)],
            'lots' => ['present', 'array', 'max:200'], 'lots.*.lot_id' => ['required', 'distinct', Rule::exists('knit_yarn_lots', 'id')->where('company_id', $company)],
            'lots.*.note' => ['nullable', 'string', 'max:300'],
        ], [
            'test_date.required' => __('knitting.yt.v.test_date'), 'test_by.required' => __('knitting.yt.v.authority'),
        ]);
        $picked = YarnStore::inOrder($data['lots']);
        if ($picked === []) {
            throw ValidationException::withMessages(['lots' => __('knitting.yt.v.no_ticks')]);
        }
        $rows = DB::transaction(function () use ($data, $picked): Collection {
            $made = collect();
            foreach ($picked as $p) {
                $lot = YarnLot::query()->findOrFail($p['lot_id']);
                // Releasing a lot again replaces the release it already had.
                KnitYarnTestResult::query()->where('basis', 'WAIVED')->whereRaw('lower(trim(lot_no)) = ?', [mb_strtolower(trim((string) $lot->lot_no))])
                    ->where('composition_id', $lot->composition_id)->where('yarn_count_id', $lot->yarn_count_id)->delete();
                $made->push(KnitYarnTestResult::query()->create([
                    'basis' => 'WAIVED', 'test_date' => $data['test_date'], 'test_by' => $data['test_by'], 'composition_id' => $lot->composition_id, 'yarn_count_id' => $lot->yarn_count_id,
                    'brand' => $lot->brand, 'supplier_id' => $lot->supplier_id, 'lot_no' => (string) $lot->lot_no, 'colour' => $lot->colour, 'result' => 'PASSED',
                    'note' => $p['note'] ?? null, 'yarn_lot_id' => $lot->id, 'created_by' => $this->caller()->auditUserId(),
                ]));
            }

            return $made;
        });

        return ApiResponse::created(['rows' => $this->rows($rows), 'message' => __('knitting.yt.done.waived', ['count' => $rows->count()])]);
    }

    /** @return list<array<string, mixed>> */
    private function rows(Collection $rows): array
    {
        $names = YarnProcurement::names();
        $people = DB::table('hr_employees')->whereIn('id', $rows->pluck('test_by')->filter()->unique())->pluck('name', 'id');
        $suppliers = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('supplier_id')->filter()->unique())->pluck('name', 'id');
        $tests = KnitYarnTest::query()->whereIn('id', $rows->pluck('test_id')->filter()->unique())->get(['id', 'test_no', 'order_id'])->keyBy('id');
        $orders = KnitYarnTestOrder::query()->whereIn('id', $tests->pluck('order_id'))->pluck('order_no', 'id');

        return $rows->map(fn (KnitYarnTestResult $r) => [
            'id' => $r->id, 'basis' => $r->basis, 'test_no' => $tests[$r->test_id]->test_no ?? null, 'order_no' => isset($tests[$r->test_id]) ? ($orders[$tests[$r->test_id]->order_id] ?? null) : null,
            'test_date' => $r->test_date->toDateString(), 'test_by' => $people[$r->test_by] ?? null,
            'yarn' => trim(($names['compositions'][$r->composition_id] ?? '').' '.($names['counts'][$r->yarn_count_id] ?? '')) ?: null,
            'count' => $names['counts'][$r->yarn_count_id] ?? null, 'colour_group' => $r->colour_group, 'brand' => $r->brand, 'supplier' => $suppliers[$r->supplier_id] ?? null,
            'lc_no' => $r->lc_no, 'lot_no' => $r->lot_no, 'colour' => $r->colour, 'result' => $r->result, 'note' => $r->note,
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(KnitYarnTest $t): array
    {
        $order = KnitYarnTestOrder::query()->findOrFail($t->order_id);
        $lines = KnitYarnTestResult::query()->where('test_id', $t->id)->orderBy('sort_order')->get();
        $names = YarnProcurement::names();

        return [
            'id' => $t->id, 'test_no' => $t->test_no, 'order_no' => $order->order_no, 'req_type' => $order->req_type, 'yarn_description' => $order->yarn_description,
            'test_date' => $t->test_date->toDateString(), 'test_by' => $t->test_by, 'remarks' => $t->remarks,
            'lines' => $lines->map(fn (KnitYarnTestResult $r) => collect($r->only(['composition_id', 'yarn_count_id', 'colour_group', 'brand', 'supplier_id', 'lc_no', 'lot_no', 'colour', 'result', 'note']))->all() + [
                'yarn' => trim(($names['compositions'][$r->composition_id] ?? '').' '.($names['counts'][$r->yarn_count_id] ?? '')) ?: null,
            ])->values()->all(),
        ];
    }
}
