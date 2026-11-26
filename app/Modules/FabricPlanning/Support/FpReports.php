<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Support;

use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\DyeHouse\Models\DyeMachine;
use App\Modules\FabricPlanning\Models\FpBatchHour;
use App\Modules\FabricPlanning\Models\FpBatchPlan;
use App\Modules\FabricPlanning\Models\FpHoliday;
use App\Modules\FabricPlanning\Models\FpMachineCapacity;
use App\Modules\FabricPlanning\Models\FpMachineRoute;
use App\Modules\FabricPlanning\Models\FpReport;
use App\Modules\FabricPlanning\Models\FpScheduleBatch;
use App\Modules\FabricPlanning\Models\FpSchedulePlan;
use App\Modules\Merchandise\Models\Buyer;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fabric Requirement → Reports (AnnotechERP 13/03): the catalogue as
 * Brickland ships it (made the first time the screen is opened), and each
 * report read live from the module's own tables — nothing cached — for a
 * unit, a buyer and a week when a run names them.
 */
final class FpReports
{
    /** code => [name, category] */
    public const CATALOGUE = [
        'FP-BATCH-PLAN' => ['Batch Plan Register', 'PLAN'],
        'FP-UNPLACED' => ['Batches Without a Machine', 'PLAN'],
        'FP-MACHINE-LOAD' => ['Machine Load vs Capacity', 'CAPACITY'],
        'FP-OVER-CAPACITY' => ['Machines Over Capacity', 'CAPACITY'],
        'FP-SCHEDULE' => ['Schedule Board Listing', 'SCHEDULE'],
        'FP-ROUTING' => ['Dye Machine Routing Register', 'CONFIGURATION'],
        'FP-HOLIDAY' => ['Factory Holiday Register', 'CONFIGURATION'],
        'FP-BATCH-HOUR' => ['Standard Batch Hour Register', 'CONFIGURATION'],
    ];

    /** code => the columns it shows (`status` last, as Brickland's status column). */
    public const COLUMNS = [
        'FP-BATCH-PLAN' => ['bp_no', 'week', 'buyer', 'style', 'colour_group', 'machine', 'batches', 'qty', 'status'],
        'FP-UNPLACED' => ['bp_no', 'week', 'buyer', 'colour_group', 'method', 'qty', 'status'],
        'FP-MACHINE-LOAD' => ['unit', 'machine', 'week', 'max_load', 'planned', 'batches', 'used', 'status'],
        'FP-OVER-CAPACITY' => ['unit', 'machine', 'week', 'max_load', 'planned', 'over_by', 'status'],
        'FP-SCHEDULE' => ['plan', 'seq', 'bp_no', 'buyer', 'machine', 'start', 'end', 'qty', 'status'],
        'FP-ROUTING' => ['colour_group', 'fabric_group', 'buyer', 'routed_machine', 'gsm_band', 'status'],
        'FP-HOLIDAY' => ['office', 'section', 'date', 'holiday_type', 'name'],
        'FP-BATCH-HOUR' => ['fiber_template', 'buyer_category', 'colour_group', 'method', 'batch_hour'],
    ];

    public static function ensure(string $companyId): void
    {
        if (FpReport::query()->exists()) {
            return;
        }
        $n = 0;
        foreach (self::CATALOGUE as $code => [$name, $category]) {
            FpReport::query()->create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'category' => $category, 'sort_order' => $n += 10, 'active' => true]);
        }
    }

    /**
     * A report's rows as shown: each a column => text map, with `tone` for the status pill.
     *
     * @param  array{factory_id?: ?string, buyer_id?: ?string, week_start?: ?string}  $f
     * @return list<array<string, mixed>>
     */
    public static function rows(string $code, array $f = []): array
    {
        $f += ['factory_id' => null, 'buyer_id' => null, 'week_start' => null];
        $week = $f['week_start'] ? FpOps::weekStart($f['week_start'])->toDateString() : null;
        $units = self::machineUnits();
        $machines = DyeMachine::query()->pluck('machine_no', 'id');
        $groups = DyeColourGroup::query()->pluck('name', 'id');
        $buyers = Buyer::query()->pluck('short_name', 'id');
        $kg = fn ($v) => round((float) $v, 2);
        $opt = fn (string $group, ?string $v) => $v === null ? __('fabricplanning.master.any') : __("fabricplanning.master.opt.{$group}.{$v}");
        $plans = fn () => FpBatchPlan::query()->when($week, fn ($q) => $q->whereDate('week_start', $week))->when($f['buyer_id'], fn ($q, $b) => $q->where('buyer_id', $b))
            ->when($f['factory_id'], fn ($q, $u) => $q->whereIn('dye_machine_id', array_keys(array_filter($units, fn ($x) => $x['id'] === $u))))->orderBy('week_start')->orderBy('bp_no')->get();

        return match ($code) {
            'FP-BATCH-PLAN' => $plans()->map(fn (FpBatchPlan $p) => [
                'bp_no' => $p->bp_no, 'week' => FpOps::weekLabel($p->week_start->toDateString()), 'buyer' => $buyers[$p->buyer_id] ?? null, 'style' => $p->style_job,
                'colour_group' => $groups[$p->colour_group_id] ?? null, 'machine' => $machines[$p->dye_machine_id] ?? null, 'batches' => $p->batches, 'qty' => $kg($p->bp_qty),
                'status' => __("fabricplanning.ops.opt.status.{$p->status}"), 'tone' => $p->status,
            ])->values()->all(),
            'FP-UNPLACED' => FpBatchPlan::query()->whereNull('dye_machine_id')->where('status', '!=', 'CANCELLED')->when($week, fn ($q) => $q->whereDate('week_start', $week))
                ->when($f['buyer_id'], fn ($q, $b) => $q->where('buyer_id', $b))->orderBy('week_start')->orderBy('bp_no')->get()->map(fn (FpBatchPlan $p) => [
                    'bp_no' => $p->bp_no, 'week' => FpOps::weekLabel($p->week_start->toDateString()), 'buyer' => $buyers[$p->buyer_id] ?? null, 'colour_group' => $groups[$p->colour_group_id] ?? null,
                    'method' => __("fabricplanning.ops.opt.method.{$p->dyeing_method}"), 'qty' => $kg($p->bp_qty), 'status' => __("fabricplanning.ops.opt.status.{$p->status}"), 'tone' => $p->status,
                ])->values()->all(),
            'FP-MACHINE-LOAD', 'FP-OVER-CAPACITY' => self::load($code, $week, $f['factory_id'], $units, $machines),
            'FP-SCHEDULE' => self::schedule($f, $units, $machines, $buyers),
            'FP-ROUTING' => FpMachineRoute::query()->when($f['buyer_id'], fn ($q, $b) => $q->where('buyer_id', $b))
                ->when($f['factory_id'], fn ($q, $u) => $q->whereIn('dye_machine_id', array_keys(array_filter($units, fn ($x) => $x['id'] === $u))))->get()
                ->map(fn (FpMachineRoute $r) => [
                    'colour_group' => $groups[$r->colour_group_id] ?? __('fabricplanning.master.any'), 'fabric_group' => $opt('fabric_group', $r->fabric_group), 'buyer' => $buyers[$r->buyer_id] ?? __('fabricplanning.master.any'),
                    'routed_machine' => $machines[$r->dye_machine_id] ?? null, 'gsm_band' => $r->gsm_from !== null ? (float) $r->gsm_from.'–'.(float) $r->gsm_to : __('fabricplanning.master.any'),
                    'status' => __($r->active ? 'erp.active' : 'erp.inactive'), 'tone' => $r->active ? 'ACTIVE' : 'INACTIVE',
                ])->sortBy('colour_group')->values()->all(),
            'FP-HOLIDAY' => self::holidays($f),
            'FP-BATCH-HOUR' => FpBatchHour::query()->orderBy('dyeing_method')->get()->map(fn (FpBatchHour $h) => [
                'fiber_template' => $opt('template', $h->fiber_template), 'buyer_category' => $opt('buyer_category', $h->buyer_category), 'colour_group' => $groups[$h->colour_group_id] ?? null,
                'method' => $opt('method', $h->dyeing_method), 'batch_hour' => (float) $h->batch_hours,
            ])->values()->all(),
            default => [],
        };
    }

    /** @return array<string, array{id: ?string, label: ?string}> dye machine => its unit (floor → building → factory) */
    private static function machineUnits(): array
    {
        return DB::table('dye_machines as m')->join('floors as f', 'f.id', '=', 'm.floor_id')->join('buildings as b', 'b.id', '=', 'f.building_id')->join('factories as fa', 'fa.id', '=', 'b.factory_id')
            ->get(['m.id', 'fa.id as unit', 'fa.name'])->mapWithKeys(fn ($r) => [$r->id => ['id' => $r->unit, 'label' => $r->name]])->all();
    }

    /**
     * @param  array<string, array{id: ?string, label: ?string}>  $units
     * @param  Collection<string, string>  $machines
     * @return list<array<string, mixed>>
     */
    private static function load(string $code, ?string $week, ?string $unit, array $units, Collection $machines): array
    {
        $caps = FpMachineCapacity::query()->when($week, fn ($q) => $q->whereDate('week_start', $week))->orderBy('week_start')->get()
            ->filter(fn ($c) => ! $unit || ($units[$c->dye_machine_id]['id'] ?? null) === $unit);
        $planned = FpBatchPlan::query()->where('status', '!=', 'CANCELLED')->whereNotNull('dye_machine_id')->selectRaw('dye_machine_id, week_start, sum(batches) as batches, sum(bp_qty) as qty')
            ->groupBy('dye_machine_id', 'week_start')->get()->keyBy(fn ($p) => $p->dye_machine_id.'|'.$p->week_start->toDateString());
        $rows = [];
        foreach ($caps as $c) {
            $p = $planned[$c->dye_machine_id.'|'.$c->week_start->toDateString()] ?? null;
            $max = round($c->capacity_kg * $c->max_load_pct / 100, 2);
            $qty = round((float) ($p->qty ?? 0), 2);
            $over = $qty > $max;
            if ($code === 'FP-OVER-CAPACITY' && ! $over) {
                continue;
            }
            $row = ['unit' => $units[$c->dye_machine_id]['label'] ?? null, 'machine' => $machines[$c->dye_machine_id] ?? null, 'week' => FpOps::weekLabel($c->week_start->toDateString()), 'max_load' => $max, 'planned' => $qty];
            $rows[] = $code === 'FP-OVER-CAPACITY'
                ? $row + ['over_by' => round($qty - $max, 2), 'status' => __('fabricplanning.ops.rpt.over'), 'tone' => 'OVER']
                : $row + ['batches' => (int) ($p->batches ?? 0), 'used' => $max > 0 ? (int) round($qty * 100 / $max) : 0, 'status' => __($over ? 'fabricplanning.ops.rpt.over' : 'fabricplanning.ops.rpt.within'), 'tone' => $over ? 'OVER' : 'WITHIN'];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  array<string, array{id: ?string, label: ?string}>  $units
     * @param  Collection<string, string>  $machines
     * @param  Collection<string, string>  $buyers
     * @return list<array<string, mixed>>
     */
    private static function schedule(array $f, array $units, Collection $machines, Collection $buyers): array
    {
        $boards = FpSchedulePlan::query()->orderBy('plan_no')->get()->keyBy('id');
        $batches = FpScheduleBatch::query()->whereIn('schedule_plan_id', $boards->keys())->orderBy('sequence')->get();
        $plans = FpBatchPlan::query()->whereIn('id', $batches->pluck('batch_plan_id'))->get()->keyBy('id');
        $week = $f['week_start'] ? FpOps::weekStart($f['week_start'])->toDateString() : null;

        return $batches->filter(function ($b) use ($plans, $f, $units, $week): bool {
            $p = $plans[$b->batch_plan_id];

            return (! $f['buyer_id'] || $p->buyer_id === $f['buyer_id']) && (! $week || $p->week_start->toDateString() === $week)
                && (! $f['factory_id'] || ($units[$b->dye_machine_id]['id'] ?? null) === $f['factory_id']);
        })->sortBy(fn ($b) => [$boards[$b->schedule_plan_id]->plan_no, $b->sequence])->map(fn ($b) => [
            'plan' => $boards[$b->schedule_plan_id]->plan_no.' — '.$boards[$b->schedule_plan_id]->name, 'seq' => $b->sequence, 'bp_no' => $plans[$b->batch_plan_id]->bp_no,
            'buyer' => $buyers[$plans[$b->batch_plan_id]->buyer_id] ?? null, 'machine' => $machines[$b->dye_machine_id] ?? null, 'start' => $b->planned_start?->toDateString(), 'end' => $b->planned_end?->toDateString(),
            'qty' => round((float) $plans[$b->batch_plan_id]->bp_qty, 2), 'status' => __("fabricplanning.ops.opt.status.{$plans[$b->batch_plan_id]->status}"), 'tone' => $plans[$b->batch_plan_id]->status,
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $f
     * @return list<array<string, mixed>>
     */
    private static function holidays(array $f): array
    {
        $company = app(TenantContext::class)->companyId();
        $offices = DB::table('factories')->where('company_id', $company)->pluck('name', 'id');
        $sections = DB::table('departments')->where('company_id', $company)->pluck('name', 'id');

        return FpHoliday::query()->when($f['factory_id'], fn ($q, $u) => $q->where('factory_id', $u))->when($f['week_start'], function ($q, $w) {
            $s = FpOps::weekStart($w);
            $q->whereBetween('day', [$s->toDateString(), $s->addDays(6)->toDateString()]);
        })->orderBy('day')->get()->map(fn (FpHoliday $h) => [
            'office' => $offices[$h->factory_id] ?? null, 'section' => $h->section_id ? ($sections[$h->section_id] ?? null) : __('fabricplanning.ops.hol.all_sections'),
            'date' => $h->day->toDateString(), 'holiday_type' => __("fabricplanning.ops.opt.day_type.{$h->day_type}"), 'name' => $h->name,
        ])->values()->all();
    }
}
