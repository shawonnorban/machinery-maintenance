<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Knitting\Models\KnitYarnTest;
use App\Modules\Knitting\Models\KnitYarnTestOrder;
use App\Modules\Knitting\Models\KnitYarnTestOrderLine;
use App\Modules\Knitting\Models\KnitYarnTestResult;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the yarn test chain (AnnotechERP 15/04) shares: the pickers, how a
 * lot is known across the chain (its Lot # with its yarn — composition and
 * count), and the answer a lot carries: the latest register row about it,
 * from the lab or from an approval without test.
 */
final class YarnTesting
{
    /** @return array<string, mixed> */
    public static function options(): array
    {
        $company = app(TenantContext::class)->companyId();
        $names = YarnProcurement::names();
        $employees = DB::table('hr_employees')->where('company_id', $company)->orderBy('name')->get(['id', 'employee_code', 'name']);
        $orders = KnitYarnTestOrder::query()->orderByDesc('order_date')->orderByDesc('order_no')->limit(500)->get();
        $lots = KnitYarnTestOrderLine::query()->whereIn('order_id', $orders->pluck('id'))->groupBy('order_id')->selectRaw('order_id, count(*) as n')->pluck('n', 'order_id');
        $people = $employees->pluck('name', 'id');

        return [
            'company' => ['value' => $company, 'label' => DB::table('companies')->where('id', $company)->value('name')],
            'stores' => YarnStore::stores()->map(fn ($s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->values()->all(),
            'employees' => $employees->map(fn ($e) => ['value' => $e->id, 'label' => "{$e->name} ({$e->employee_code})"])->values()->all(),
            'suppliers' => DB::table('mp_suppliers')->where('company_id', $company)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'counts' => KnitYarnCount::query()->where('active', true)->orderBy('display_order')->orderBy('description')->get()
                ->map(fn (KnitYarnCount $c) => ['value' => $c->id, 'label' => (string) $c->description])->values()->all(),
            'colour_groups' => YarnProcurement::COLOURS,
            'uoms' => array_map(fn (array $u) => ['value' => $u['code'], 'label' => $u['code']], UomCodes::options($company)),
            'req_types' => YarnStore::REQ_TYPES,
            'statuses' => KnitYarnTestOrder::STATUSES,
            'results' => KnitYarnTestResult::RESULTS,
            // Every lot the stores have held: what Yarn Item and Brand pick from.
            'lots' => array_map(fn (array $l) => collect($l)->only(['lot_id', 'store_id', 'yarn', 'composition_id', 'yarn_count_id', 'colour', 'brand', 'lot_no', 'supplier_id', 'balance'])->all(),
                YarnStore::lots(null, false)),
            'orders' => $orders->map(fn (KnitYarnTestOrder $o) => [
                'value' => $o->order_no, 'label' => $o->order_no.' — '.$o->yarn_description, 'order_date' => $o->order_date->toDateString(), 'req_type' => $o->req_type,
                'requested_by' => $people[$o->requested_by] ?? null, 'yarn_description' => $o->yarn_description, 'lots' => (int) ($lots[$o->id] ?? 0), 'status' => $o->status,
            ])->values()->all(),
            'tests' => KnitYarnTest::query()->orderByDesc('test_date')->orderByDesc('test_no')->limit(300)->get(['test_no', 'order_id', 'test_date'])
                ->map(fn (KnitYarnTest $t) => ['value' => $t->test_no, 'label' => $t->test_no.' · '.KnitYarnTestOrder::query()->whereKey($t->order_id)->value('order_no').' — '.$t->test_date->format('d M Y')])->values()->all(),
            'yarn_names' => ['compositions' => $names['compositions'], 'counts' => $names['counts']],
        ];
    }

    /** How a lot is known across the chain: its Lot # and its yarn. */
    public static function key(?string $lotNo, ?string $compositionId, ?string $countId): string
    {
        return mb_strtolower(trim((string) $lotNo)).'|'.$compositionId.'|'.$countId;
    }

    /**
     * The answer each lot carries: the latest register row about it.
     *
     * @param  Collection<int, array<string, mixed>>|array<int, array<string, mixed>>  $lots  rows with lot_no, composition_id, yarn_count_id
     * @return array<string, KnitYarnTestResult>
     */
    public static function answers($lots): array
    {
        $lotNos = collect($lots)->pluck('lot_no')->filter()->map(fn ($n) => mb_strtolower(trim((string) $n)))->unique()->values();
        if ($lotNos->isEmpty()) {
            return [];
        }
        $out = [];
        KnitYarnTestResult::query()->whereIn(DB::raw('lower(trim(lot_no))'), $lotNos->all())->orderBy('test_date')->orderBy('created_at')->get()
            ->each(function (KnitYarnTestResult $r) use (&$out): void {
                $out[self::key($r->lot_no, $r->composition_id, $r->yarn_count_id)] = $r;
            });

        return $out;
    }

    /**
     * A lot's answer from `answers()`: the row about this lot and yarn, else
     * one filed against the Lot # with no yarn named on it.
     *
     * @param  array<string, KnitYarnTestResult>  $answers
     */
    public static function answerFor(array $answers, ?string $lotNo, ?string $compositionId, ?string $countId): ?KnitYarnTestResult
    {
        return $answers[self::key($lotNo, $compositionId, $countId)] ?? $answers[self::key($lotNo, null, null)] ?? null;
    }

    /** "2 Passed · 1 Pending": what a test order's lots came to. */
    public static function summary(KnitYarnTestOrder $o): ?string
    {
        $test = KnitYarnTest::query()->where('order_id', $o->id)->value('id');
        if (! $test) {
            return null;
        }
        $counts = KnitYarnTestResult::query()->where('test_id', $test)->groupBy('result')->selectRaw('result, count(*) as n')->pluck('n', 'result');

        return collect(['PASSED', 'CONDITIONAL', 'FAILED', 'PENDING'])->filter(fn ($r) => isset($counts[$r]))
            ->map(fn ($r) => $counts[$r].' '.__("knitting.yt.result.{$r}"))->implode(' · ') ?: null;
    }
}
