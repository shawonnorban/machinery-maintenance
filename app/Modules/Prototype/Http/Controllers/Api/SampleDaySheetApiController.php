<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleDaySheet;
use App\Modules\Prototype\Models\SampleDaySheetLine;
use App\Modules\Prototype\Models\SampleRequisition;
use App\Modules\Prototype\Support\Prototype;
use App\Modules\Prototype\Support\SampleCards;
use App\Modules\Prototype\Support\SampleLedger;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Prototype Desk → Sample Room Daily Target → Target Setting (AnnotechERP
 * 07/03): the room's sheet for one day, addressed by its date — one per date —
 * with its capacity (available manpower × shift hours, the hands present no
 * more than the total) and a target per sample line. Saving replaces the
 * lines with exactly the grid, which needs at least one; Total Target,
 * Average WH and planned hours come from the lines, and a sheet planned past
 * its capacity saves with a warning. A closed sheet is locked and kept.
 */
class SampleDaySheetApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('prototype.target.view');
        $company = app(TenantContext::class)->companyId();
        $orders = Order::query()->orderByDesc('created_at')->get(['id', 'order_no', 'style_id']);
        $styles = Style::query()->whereIn('id', $orders->pluck('style_id'))->get(['id', 'style_ref', 'style_ext'])->keyBy('id');
        $style = fn ($id): string => ($s = $styles[$id] ?? null) ? trim($s->style_ref.($s->style_ext ? "-{$s->style_ext}" : '')) : '';

        return ApiResponse::ok([
            'users' => Prototype::users(), 'shifts' => SampleDaySheet::SHIFTS, 'states' => SampleDaySheet::STATES, 'priorities' => SampleDaySheetLine::PRIORITIES,
            'companies' => [['value' => $company, 'label' => Company::query()->whereKey($company)->value('name')]],
            'lines' => PlanningLine::query()->where('active', true)->orderBy('display_order')->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn ($l): array => ['value' => $l->id, 'label' => "{$l->code} — {$l->name}"])->all(),
            'samples' => SampleCards::options(Sample::query()->where('state', '!=', 'CANCELLED')->orderByDesc('sample_no')->get()),
            'sample_types' => SampleType::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($t): array => ['value' => $t->id, 'label' => "{$t->name} ({$t->code})"])->all(),
            'orders' => $orders->map(fn ($o): array => ['value' => $o->id, 'label' => trim($style($o->style_id).' — '.$o->order_no, ' —')])->all(),
            'rooms' => SampleDaySheet::query()->whereNotNull('sample_room')->distinct()->orderBy('sample_room')->pluck('sample_room')->all(),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->allow('prototype.target.view');

        return ApiResponse::ok($this->present(SampleDaySheet::query()->orderByDesc('target_date')->get()));
    }

    public function show(string $date): JsonResponse
    {
        $this->allow('prototype.target.view');

        return ApiResponse::ok($this->present(collect([$this->find($date)]), true)[0]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('prototype.target.manage');
        $data = $this->validated($request, null);
        $row = DB::transaction(function () use ($data): SampleDaySheet {
            $row = SampleDaySheet::query()->create([...collect($data)->except('lines')->all(), 'state' => $data['state'] ?? 'DRAFT']);
            $this->writeLines($row, $data['lines']);

            return $row;
        });

        return ApiResponse::created($this->withWarning($this->present(collect([$row->fresh()]), true)[0]));
    }

    public function update(Request $request, string $date): JsonResponse
    {
        $this->allow('prototype.target.manage');
        $row = $this->find($date);
        if ($row->state === 'CLOSED') {
            throw ValidationException::withMessages(['state' => __('prototype.target.locked')]);
        }
        $data = $this->validated($request, $row);
        DB::transaction(function () use ($row, $data): void {
            $row->update([...collect($data)->except('lines')->all(), 'state' => $data['state'] ?? $row->state]);
            $this->writeLines($row, $data['lines']);
        });

        return ApiResponse::ok($this->withWarning($this->present(collect([$row->fresh()]), true)[0]));
    }

    public function destroy(string $date): JsonResponse
    {
        $this->allow('prototype.target.manage');
        $row = $this->find($date);
        if ($row->state === 'CLOSED') {
            throw ValidationException::withMessages(['state' => __('prototype.target.remove_closed')]);
        }
        $row->delete();

        return ApiResponse::ok(['removed' => $date]);
    }

    /** Saving replaces the day's lines with exactly the grid; a line's sample fills its type and order when they are left blank. */
    private function writeLines(SampleDaySheet $row, array $lines): void
    {
        SampleDaySheetLine::query()->where('day_sheet_id', $row->id)->delete();
        $samples = Sample::query()->whereIn('id', collect($lines)->pluck('sample_id')->filter())->get(['id', 'requisition_id'])->keyBy('id');
        $reqs = SampleRequisition::query()->whereIn('id', $samples->pluck('requisition_id'))->get(['id', 'sample_type_id', 'order_id'])->keyBy('id');
        foreach (array_values($lines) as $i => $line) {
            $req = ($s = $samples[$line['sample_id'] ?? ''] ?? null) ? ($reqs[$s->requisition_id] ?? null) : null;
            SampleDaySheetLine::query()->create([
                ...$line, 'day_sheet_id' => $row->id, 'sort_order' => $i + 1, 'target' => (int) ($line['target'] ?? 0), 'manpower' => (int) ($line['manpower'] ?? 0),
                'sample_type_id' => $line['sample_type_id'] ?? $req?->sample_type_id, 'order_id' => $line['order_id'] ?? $req?->order_id,
            ]);
        }
    }

    /** A sheet planned past its capacity still saves, with the warning — and the desk is alerted until it is brought back within. */
    private function withWarning(array $out): array
    {
        $doc = 'CAP-'.$out['target_date'];
        $out['over_capacity']
            ? SampleLedger::raise('CAPACITY_OVERLOAD', 'WARNING', null, 'DESK', $doc, ['date' => $out['target_date'], 'planned' => $out['planned_hrs'], 'capacity' => $out['capacity_hrs']])
            : SampleLedger::resolve('CAPACITY_OVERLOAD', $doc);

        return $out + ['warning' => $out['over_capacity'] ? __('prototype.target.over_capacity', ['planned' => $out['planned_hrs'], 'capacity' => $out['capacity_hrs']]) : null];
    }

    private function find(string $date): SampleDaySheet
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1, 404);

        return SampleDaySheet::query()->whereDate('target_date', $date)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?SampleDaySheet $row): array
    {
        $company = app(TenantContext::class)->companyId();
        $user = ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)];
        $in = fn (string $table) => ['nullable', Rule::exists($table, 'id')->where('company_id', $company)];
        $hours = ['nullable', 'numeric', 'min:0', 'max:9999'];
        $data = $request->validate([
            'target_date' => ['required', 'date'],
            'sample_room' => ['nullable', 'string', 'max:120'],
            'shift' => ['nullable', Rule::in(SampleDaySheet::SHIFTS)],
            'unit_company_id' => ['nullable', Rule::in([$company])],
            'supervisor_id' => $user, 'prepared_by' => $user,
            'state' => ['nullable', Rule::in(SampleDaySheet::STATES)],
            'total_manpower' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'available_manpower' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'shift_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'remarks' => ['nullable', 'string', 'max:4000'],
            'lines' => ['nullable', 'array'],
            'lines.*.planning_line_id' => ['required', Rule::exists('planning_lines', 'id')->where('company_id', $company)],
            'lines.*.sample_id' => $in('proto_samples'),
            'lines.*.sample_type_id' => $in('sample_types'),
            'lines.*.order_id' => $in('merch_orders'),
            'lines.*.target' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'lines.*.wh' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'lines.*.manpower' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'lines.*.target_hrs' => $hours,
            'lines.*.actual' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'lines.*.actual_hrs' => $hours,
            'lines.*.start_time' => ['nullable', 'date_format:H:i'],
            'lines.*.end_time' => ['nullable', 'date_format:H:i'],
            'lines.*.team' => ['nullable', 'string', 'max:120'],
            'lines.*.priority' => ['nullable', Rule::in(SampleDaySheetLine::PRIORITIES)],
            'lines.*.remarks' => ['nullable', 'string', 'max:500'],
        ], ['target_date.required' => __('prototype.target.date_required'), 'lines.*.planning_line_id.required' => __('prototype.target.line_required')]);
        $data['lines'] = $data['lines'] ?? [];
        ksort($data['lines']);
        $taken = SampleDaySheet::query()->whereDate('target_date', $data['target_date'])->when($row, fn ($q) => $q->whereKeyNot($row->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['target_date' => __('prototype.target.date_taken', ['date' => CarbonImmutable::parse($data['target_date'])->format('d M Y')])]);
        }
        if ((int) ($data['available_manpower'] ?? 0) > (int) ($data['total_manpower'] ?? 0)) {
            throw ValidationException::withMessages(['available_manpower' => __('prototype.target.available_over_total')]);
        }
        if (empty($data['lines'])) {
            throw ValidationException::withMessages(['lines' => __('prototype.target.lines_required')]);
        }
        foreach (array_values($data['lines']) as $i => $line) {
            if (! empty($line['start_time']) && ! empty($line['end_time']) && $line['end_time'] <= $line['start_time']) {
                throw ValidationException::withMessages(['lines' => __('prototype.target.time_order', ['row' => $i + 1])]);
            }
        }
        $data['shift_hours'] = $data['shift_hours'] ?? 8;
        $data['total_manpower'] = (int) ($data['total_manpower'] ?? 0);
        $data['available_manpower'] = (int) ($data['available_manpower'] ?? 0);

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function present(Collection $rows, bool $detail = false): array
    {
        $lines = SampleDaySheetLine::query()->whereIn('day_sheet_id', $rows->pluck('id'))->orderBy('sort_order')->get()->groupBy('day_sheet_id');
        $names = Prototype::names([...$rows->pluck('supervisor_id'), ...$rows->pluck('prepared_by')]);
        $companies = Company::query()->whereIn('id', $rows->pluck('unit_company_id')->filter())->pluck('name', 'id');
        $today = CarbonImmutable::today();

        return $rows->map(function (SampleDaySheet $r) use ($lines, $names, $companies, $today, $detail): array {
            $list = $lines[$r->id] ?? collect();
            $out = ['id' => $r->id, 'target_date' => $r->target_date->toDateString(), 'sample_room' => $r->sample_room, 'shift' => $r->shift,
                'unit_company_id' => $r->unit_company_id, 'company' => $r->unit_company_id ? ($companies[$r->unit_company_id] ?? null) : null,
                'supervisor_id' => $r->supervisor_id, 'supervisor_name' => $names[$r->supervisor_id] ?? null, 'prepared_by' => $r->prepared_by, 'prepared_by_name' => $names[$r->prepared_by] ?? null,
                'state' => $r->state, 'total_manpower' => $r->total_manpower, 'available_manpower' => $r->available_manpower, 'shift_hours' => (float) $r->shift_hours, 'remarks' => $r->remarks,
                'overdue' => $r->target_date->lt($today) && $r->state !== 'CLOSED', 'locked' => $r->state === 'CLOSED',
            ] + self::figures($list, (float) $r->available_manpower * (float) $r->shift_hours);
            if ($detail) {
                $out['lines'] = $list->map(fn (SampleDaySheetLine $l): array => [
                    ...$l->only(['planning_line_id', 'sample_id', 'sample_type_id', 'order_id', 'target', 'manpower', 'actual', 'team', 'priority', 'remarks']),
                    'wh' => $l->wh === null ? null : (float) $l->wh, 'target_hrs' => $l->target_hrs === null ? null : (float) $l->target_hrs, 'actual_hrs' => $l->actual_hrs === null ? null : (float) $l->actual_hrs,
                    'start_time' => $l->start_time ? substr((string) $l->start_time, 0, 5) : null, 'end_time' => $l->end_time ? substr((string) $l->end_time, 0, 5) : null,
                ])->values()->all();
            }

            return $out;
        })->values()->all();
    }

    /**
     * What a sheet's lines come to: Total Target and Actual, Achievement
     * (actual over target), Average WH, planned hours (the lines' target
     * hours) against capacity, and Efficiency — the hours earned (each line's
     * target hours in proportion to what it made) over the hours it took.
     *
     * @return array<string, mixed>
     */
    public static function figures(Collection $lines, float $capacity): array
    {
        $target = (int) $lines->sum('target');
        $actual = (int) $lines->sum(fn ($l) => (int) $l->actual);
        $planned = round((float) $lines->sum(fn ($l) => (float) $l->target_hrs), 2);
        $actualHrs = (float) $lines->sum(fn ($l) => (float) $l->actual_hrs);
        $earned = (float) $lines->sum(fn ($l) => $l->target > 0 ? (float) $l->target_hrs * (int) $l->actual / $l->target : 0);
        $wh = $lines->filter(fn ($l) => $l->wh !== null);

        return [
            'lines_count' => $lines->count(), 'total_target' => $target, 'total_actual' => $actual, 'lines_manpower' => (int) $lines->sum('manpower'),
            'achievement' => $target > 0 ? round($actual / $target * 100, 1) : null,
            'average_wh' => $wh->isEmpty() ? null : round((float) $wh->avg(fn ($l) => (float) $l->wh), 2),
            'planned_hrs' => $planned, 'capacity_hrs' => round($capacity, 2), 'over_capacity' => $planned > round($capacity, 2),
            'efficiency' => $actualHrs > 0 ? round($earned / $actualHrs * 100, 1) : null, 'earned_hrs' => round($earned, 2), 'actual_hrs' => round($actualHrs, 2),
        ];
    }
}
