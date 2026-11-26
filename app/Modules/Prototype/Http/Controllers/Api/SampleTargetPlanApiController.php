<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Prototype\Models\SampleDaySheet;
use App\Modules\Prototype\Models\SampleDaySheetLine;
use App\Modules\Prototype\Models\SampleTargetPlan;
use App\Modules\Prototype\Support\Prototype;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Prototype Desk → Sample Room Daily Target → Weekly / Monthly Plan
 * (AnnotechERP 07/03): the room's target for a day, a week or a month
 * (TP-2026-00001). A daily plan is one day, a weekly one exactly seven and a
 * monthly one a calendar month. Actual is what the day sheets inside the
 * period made — for its room, when the plan names one. Planned hours past the
 * available hours save with a warning.
 */
class SampleTargetPlanApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('prototype.target.view');

        return ApiResponse::ok([
            'users' => Prototype::users(), 'periods' => SampleTargetPlan::PERIODS,
            'sample_types' => SampleType::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($t): array => ['value' => $t->id, 'label' => "{$t->name} ({$t->code})"])->all(),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->allow('prototype.target.view');

        return ApiResponse::ok($this->present(SampleTargetPlan::query()->orderByDesc('period_start')->orderByDesc('plan_no')->get()));
    }

    public function show(string $plan): JsonResponse
    {
        $this->allow('prototype.target.view');

        return ApiResponse::ok($this->present(collect([$this->find($plan)]), true)[0]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('prototype.target.manage');
        $data = $this->validated($request);
        $row = SampleTargetPlan::query()->create([...$data, 'plan_no' => Prototype::nextNumber('TP', 'proto_target_plans', 'plan_no', $data['period_start'])]);

        return ApiResponse::created($this->withWarning($this->present(collect([$row->fresh()]), true)[0]));
    }

    public function update(Request $request, string $plan): JsonResponse
    {
        $this->allow('prototype.target.manage');
        $row = $this->find($plan);
        $row->update($this->validated($request));

        return ApiResponse::ok($this->withWarning($this->present(collect([$row->fresh()]), true)[0]));
    }

    private function withWarning(array $out): array
    {
        return $out + ['warning' => $out['planned_hours'] > $out['available_hours'] ? __('prototype.plan.over_hours', ['planned' => $out['planned_hours'], 'available' => $out['available_hours']]) : null];
    }

    private function find(string $plan): SampleTargetPlan
    {
        return SampleTargetPlan::query()->where('plan_no', $plan)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'period' => ['required', Rule::in(SampleTargetPlan::PERIODS)],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'sample_room' => ['nullable', 'string', 'max:120'],
            'team' => ['nullable', 'string', 'max:120'],
            'supervisor_id' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'sample_type_id' => ['nullable', Rule::exists('sample_types', 'id')->where('company_id', $company)],
            'target_qty' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'available_manpower' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'available_hours' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'planned_hours' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'remarks' => ['nullable', 'string', 'max:4000'],
        ], ['period.required' => __('prototype.plan.period_required'), 'period_start.required' => __('prototype.plan.start_required'), 'period_end.required' => __('prototype.plan.end_required')]);
        $start = CarbonImmutable::parse($data['period_start']);
        $end = CarbonImmutable::parse($data['period_end']);
        $fits = match ($data['period']) {
            'DAILY' => $end->equalTo($start),
            'WEEKLY' => $end->equalTo($start->addDays(6)),
            'MONTHLY' => $start->day === 1 && $end->equalTo($start->endOfMonth()->startOfDay()),
        };
        if (! $fits) {
            throw ValidationException::withMessages(['period_end' => __('prototype.plan.span_'.strtolower($data['period']))]);
        }
        foreach (['target_qty', 'available_manpower', 'available_hours', 'planned_hours'] as $k) {
            $data[$k] = $data[$k] ?? 0;
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function present(Collection $rows, bool $detail = false): array
    {
        $names = Prototype::names($rows->pluck('supervisor_id')->all());
        $types = SampleType::query()->whereIn('id', $rows->pluck('sample_type_id')->filter())->pluck('name', 'id');

        return $rows->map(function (SampleTargetPlan $r) use ($names, $types, $detail): array {
            // The day sheets dated inside the period — for its room, when the plan names one.
            $sheets = SampleDaySheet::query()->whereBetween('target_date', [$r->period_start->toDateString(), $r->period_end->toDateString()])
                ->when($r->sample_room, fn ($q) => $q->whereRaw('LOWER(sample_room) = ?', [mb_strtolower($r->sample_room)]))->orderBy('target_date')->get();
            $lines = SampleDaySheetLine::query()->whereIn('day_sheet_id', $sheets->pluck('id'))->get()->groupBy('day_sheet_id');
            $actual = (int) $lines->flatten()->sum(fn ($l) => (int) $l->actual);
            $out = [
                'id' => $r->id, 'plan_no' => $r->plan_no, 'period' => $r->period, 'period_start' => $r->period_start->toDateString(), 'period_end' => $r->period_end->toDateString(),
                'sample_room' => $r->sample_room, 'team' => $r->team, 'supervisor_id' => $r->supervisor_id, 'supervisor_name' => $names[$r->supervisor_id] ?? null,
                'sample_type_id' => $r->sample_type_id, 'sample_type' => $r->sample_type_id ? ($types[$r->sample_type_id] ?? null) : null,
                'target_qty' => $r->target_qty, 'available_manpower' => $r->available_manpower, 'available_hours' => (float) $r->available_hours, 'planned_hours' => (float) $r->planned_hours,
                'remarks' => $r->remarks, 'actual' => $actual, 'achievement' => $r->target_qty > 0 ? round($actual / $r->target_qty * 100, 1) : null, 'day_sheets_count' => $sheets->count(),
            ];
            if ($detail) {
                $out['day_sheets'] = $sheets->map(fn (SampleDaySheet $s): array => [
                    'target_date' => $s->target_date->toDateString(), 'sample_room' => $s->sample_room, 'shift' => $s->shift, 'state' => $s->state,
                ] + SampleDaySheetApiController::figures($lines[$s->id] ?? collect(), (float) $s->available_manpower * (float) $s->shift_hours))->values()->all();
            }

            return $out;
        })->values()->all();
    }
}
