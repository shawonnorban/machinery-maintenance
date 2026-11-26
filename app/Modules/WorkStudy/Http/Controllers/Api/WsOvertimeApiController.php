<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Http\Controllers\Api;

use App\Modules\Planning\Models\PlanningLine;
use App\Modules\WorkStudy\Models\WsOtPlan;
use App\Modules\WorkStudy\Models\WsSalaryRate;
use App\Modules\WorkStudy\Support\WsOvertime;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Pdf\TablePdf;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Work Study → OT & Salary (AnnotechERP 14/06), as Brickland keeps it:
 *
 * - Monthly Salary & OT Rates → Cost Particulars: one company's average
 *   operator and ASO salary and overtime rate for every month of a year on
 *   one sheet; a month left at zero throughout leaves the register when the
 *   sheet is saved, so a year is corrected by clearing it;
 * - Garment Overtime Plan → OT Plan: every manpower group on a floor for a
 *   day, and how many operators and ASOs stay to each hour between 6PM and
 *   5AM; a group planned at no hours writes nothing, so a plan is withdrawn by
 *   clearing its boxes.
 *
 * A year travels as 2026, a floor by its code, a day as 2026-10-25. Both
 * sheets print (PDF) and export (Excel).
 */
class WsOvertimeApiController extends ApiController
{
    private const RATES = ['op_salary', 'aso_salary', 'op_ot_rate', 'aso_ot_rate'];

    /** What the forms and sheets choose from. */
    public function options(): JsonResponse
    {
        $this->allow('workstudy.ot.view');
        $years = WsSalaryRate::query()->pluck('period')->map(fn ($p) => (int) substr((string) $p, 0, 4))
            ->push((int) now()->format('Y'), (int) now()->format('Y') + 1)->unique()->sort()->values()->all();
        $floors = DB::table('floors')->where('company_id', $this->company())->orderBy('name')->orderBy('code')->get(['id', 'name', 'code']);
        $departments = DB::table('departments')->where('company_id', $this->company())->orderBy('name')->get(['id', 'name']);
        $deptName = $departments->pluck('name', 'id');

        return ApiResponse::ok([
            'company' => ['value' => $this->company(), 'label' => DB::table('companies')->where('id', $this->company())->value('name')],
            'years' => $years,
            'floors' => $floors->map(fn ($f) => ['value' => $f->id, 'code' => $f->code, 'label' => "{$f->name} — {$f->code}"])->values()->all(),
            // A line names its floor ("1st Floor — LINE01") and is offered only under it.
            'lines' => PlanningLine::query()->whereIn('floor_id', $floors->pluck('id'))->where('active', true)->orderBy('display_order')->orderBy('code')->get()
                ->map(fn (PlanningLine $l) => ['value' => $l->id, 'floor_id' => $l->floor_id, 'label' => ($floors->firstWhere('id', $l->floor_id)?->name ?? '').' — '.$l->code])->values()->all(),
            'departments' => $departments->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->values()->all(),
            'designations' => DB::table('designations')->where('company_id', $this->company())->orderBy('name')->get(['id', 'name', 'department_id'])
                ->map(fn ($d) => ['value' => $d->id, 'label' => $d->name.($d->department_id && isset($deptName[$d->department_id]) ? " — {$deptName[$d->department_id]}" : '')])->values()->all(),
            'slots' => collect(WsOvertime::SLOTS)->map(fn ($label, $slot) => ['slot' => (string) $slot, 'label' => WsOvertime::slotLabel($slot)])->values()->all(),
        ]);
    }

    /* ---------------- Cost Particulars ---------------- */

    /** A year's twelve months, costed or not. */
    public function rates(Request $request): JsonResponse
    {
        $this->allow('workstudy.ot.view');

        return ApiResponse::ok(['year' => $this->year($request->query('year')), 'rows' => $this->rateRows($this->year($request->query('year')))]);
    }

    /** Save the year's sheet: a month with all four rates is kept, a month at zero throughout is removed, a month half filled is refused. */
    public function saveRates(Request $request): JsonResponse
    {
        $this->allow('workstudy.ot.manage');
        $year = $this->year($request->input('year'));
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.month' => ['required', 'integer', 'min:1', 'max:12', 'distinct'],
            ...collect(self::RATES)->mapWithKeys(fn ($k) => ["rows.*.{$k}" => ['nullable', 'numeric', 'min:0', 'max:99999999']])->all(),
        ]);
        $errors = [];
        foreach ($data['rows'] as $i => $r) {
            $filled = collect(self::RATES)->filter(fn ($k) => (float) ($r[$k] ?? 0) > 0)->count();
            if ($filled > 0 && $filled < 4) {
                $errors["rows.{$i}.op_salary"] = __('workstudy.ot.v.rates_month', ['month' => Carbon::create($year, (int) $r['month'], 1)->format('F')]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($data, $year): void {
            foreach ($data['rows'] as $r) {
                $period = sprintf('%04d-%02d', $year, (int) $r['month']);
                $row = WsSalaryRate::query()->where('period', $period)->first();
                if ((float) ($r['op_salary'] ?? 0) <= 0) {
                    $row?->delete();

                    continue;
                }
                $values = collect($r)->only(self::RATES)->all();
                $row ? $row->update($values) : WsSalaryRate::query()->create($values + ['company_id' => $this->company(), 'period' => $period]);
            }
        });

        return ApiResponse::ok(['year' => $year, 'rows' => $this->rateRows($year)]);
    }

    /* ---------------- OT Plan ---------------- */

    /** The OT Plan sheet for a floor and day. */
    public function plan(Request $request): JsonResponse
    {
        $this->allow('workstudy.ot.view');
        [$floor, $date] = $this->day($request);

        return ApiResponse::ok(['rows' => WsOvertime::groups($floor->id, $date)]);
    }

    /** Save the OT Plan sheet: each group as planned; a group planned at no hours writes nothing (and an earlier plan of it goes). */
    public function savePlan(Request $request): JsonResponse
    {
        $this->allow('workstudy.ot.manage');
        [$floor, $date] = $this->day($request);
        $count = ['nullable', 'integer', 'min:0', 'max:9999'];
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.planning_line_id' => ['nullable', Rule::exists('planning_lines', 'id')->where('company_id', $this->company())->where('floor_id', $floor->id)],
            'rows.*.department_id' => ['nullable', Rule::exists('departments', 'id')->where('company_id', $this->company())],
            'rows.*.designation_id' => ['nullable', Rule::exists('designations', 'id')->where('company_id', $this->company())],
            'rows.*.mp_gen' => $count,
            'rows.*.wh_gen' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'rows.*.slots' => ['nullable', 'array'],
            'rows.*.slots.*.op' => $count,
            'rows.*.slots.*.aso' => $count,
            'rows.*.remarks' => ['nullable', 'string', 'max:255'],
        ], ['rows.*.wh_gen.max' => __('workstudy.ot.v.wh')]);
        $errors = [];
        foreach ($data['rows'] as $i => $r) {
            $planned = WsOvertime::clean($r['slots'] ?? []);
            $staying = collect($planned)->max(fn ($s) => $s['op'] + $s['aso']) ?? 0;
            if ($planned && $r['mp_gen'] !== null && $staying > (int) $r['mp_gen']) {
                $errors["rows.{$i}.mp_gen"] = __('workstudy.ot.v.more_than_mp', ['n' => $i + 1]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($data, $floor, $date): void {
            $saved = WsOtPlan::query()->where('floor_id', $floor->id)->whereDate('plan_date', $date)->get()
                ->keyBy(fn (WsOtPlan $p) => WsOvertime::key($p->planning_line_id, $p->department_id, $p->designation_id));
            foreach ($data['rows'] as $r) {
                $key = WsOvertime::key($r['planning_line_id'] ?? null, $r['department_id'] ?? null, $r['designation_id'] ?? null);
                $row = $saved->get($key);
                $slots = WsOvertime::clean($r['slots'] ?? []);
                if (! $slots) {
                    $row?->delete();

                    continue;
                }
                $values = ['mp_gen' => (int) ($r['mp_gen'] ?? 0), 'wh_gen' => $r['wh_gen'] ?? null, 'slots' => $slots, 'remarks' => $r['remarks'] ?? null];
                $row ? $row->update($values) : WsOtPlan::query()->create($values + [
                    'company_id' => $this->company(), 'floor_id' => $floor->id, 'plan_date' => $date,
                    'planning_line_id' => $r['planning_line_id'] ?? null, 'department_id' => $r['department_id'] ?? null, 'designation_id' => $r['designation_id'] ?? null,
                    'created_by' => $this->caller()->auditUserId(),
                ]);
            }
        });

        return ApiResponse::ok(['rows' => WsOvertime::groups($floor->id, $date)]);
    }

    /* ---------------- PDF and Excel ---------------- */

    /** The year's rates (`rates`) or a floor's OT plan (`ot-plan`) as a PDF or a workbook. */
    public function export(Request $request, string $sheet, string $format): Response|StreamedResponse
    {
        $this->allow('workstudy.ot.view');
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        [$title, $columns, $rows, $meta, $name] = match ($sheet) {
            'rates' => $this->ratesTable($request),
            'ot-plan' => $this->planTable($request),
            default => abort(404),
        };
        if ($format === 'pdf') {
            $subtitle = DB::table('companies')->where('id', $this->company())->value('name').' · '.trans_choice('workstudy.cost.rows', count($rows), ['count' => count($rows)]);
            $body = (new TablePdf($title, $columns, $rows, $meta, null, $subtitle))->output();

            return response($body, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'.pdf"']);
        }

        return response()->streamDownload(function () use ($columns, $rows): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn ($c) => $c['label'], $columns)));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($v) => $v ?? '', $r)));
            }
            $writer->close();
        }, $name.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return array{0: string, 1: list<array{label: string, numeric?: bool}>, 2: list<list<mixed>>, 3: array<string, string>, 4: string} */
    private function ratesTable(Request $request): array
    {
        $year = $this->year($request->query('year'));
        $columns = [['label' => __('workstudy.ot.rate.col.month')], ...array_map(fn ($k) => ['label' => __("workstudy.ot.rate.col.{$k}"), 'numeric' => true], self::RATES)];
        $rows = array_values(array_map(fn ($r) => [Carbon::create($year, $r['month'], 1)->format('F'), ...array_map(fn ($k) => (float) $r[$k], self::RATES)], array_filter($this->rateRows($year), fn ($r) => $r['costed'])));

        return [__('workstudy.ot.rate.title'), $columns, $rows, [__('workstudy.ot.rate.year') => (string) $year], "salary-ot-rates-{$year}"];
    }

    /** @return array{0: string, 1: list<array{label: string, numeric?: bool}>, 2: list<list<mixed>>, 3: array<string, string>, 4: string} */
    private function planTable(Request $request): array
    {
        [$floor, $date] = $this->day($request);
        $columns = [['label' => __('workstudy.ot.plan.col.department')], ['label' => __('workstudy.ot.plan.col.line')], ['label' => __('workstudy.ot.plan.col.designation')],
            ['label' => __('workstudy.ot.plan.col.mp_gen'), 'numeric' => true], ['label' => __('workstudy.ot.plan.col.prev_wh'), 'numeric' => true], ['label' => __('workstudy.ot.plan.col.wh_gen'), 'numeric' => true]];
        foreach (array_keys(WsOvertime::SLOTS) as $slot) {
            $columns[] = ['label' => WsOvertime::slotLabel($slot).' OP', 'numeric' => true];
            $columns[] = ['label' => WsOvertime::slotLabel($slot).' ASO', 'numeric' => true];
        }
        $columns[] = ['label' => __('workstudy.ot.plan.col.remarks')];
        $rows = [];
        foreach (WsOvertime::groups($floor->id, $date) as $g) {
            if (! $g['slots']) {
                continue;
            }
            $row = [$g['department'], $g['line'], $g['designation'], $g['mp_gen'], $g['prev_wh'], $g['wh_gen']];
            foreach (array_keys(WsOvertime::SLOTS) as $slot) {
                $row[] = $g['slots'][(string) $slot]['op'] ?? null;
                $row[] = $g['slots'][(string) $slot]['aso'] ?? null;
            }
            $row[] = $g['remarks'];
            $rows[] = $row;
        }

        return [__('workstudy.ot.plan.title'), $columns, $rows, [__('workstudy.cost.day.floor') => "{$floor->name} — {$floor->code}", __('workstudy.cost.day.date') => $date], "ot-plan-{$floor->code}-{$date}"];
    }

    /* ---------------- helpers ---------------- */

    private function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /** @return list<array<string, mixed>> */
    private function rateRows(int $year): array
    {
        $saved = WsSalaryRate::query()->where('period', 'like', "{$year}-%")->get()->keyBy('period');

        return array_map(function (int $m) use ($saved, $year) {
            $r = $saved->get(sprintf('%04d-%02d', $year, $m));

            return ['month' => $m, 'costed' => (bool) $r, ...collect(self::RATES)->mapWithKeys(fn ($k) => [$k => $r ? (float) $r->{$k} : 0.0])->all()];
        }, range(1, 12));
    }

    private function year(mixed $year): int
    {
        if (! is_numeric($year) || (int) $year < 2000 || (int) $year > 2100) {
            throw ValidationException::withMessages(['year' => __('workstudy.ot.v.year')]);
        }

        return (int) $year;
    }

    /**
     * The floor (by its code) and day the OT Plan is about.
     *
     * @return array{0: object, 1: string}
     */
    private function day(Request $request): array
    {
        $code = (string) $request->query('floor', '');
        $date = (string) $request->query('date', '');
        $floor = $code !== '' ? DB::table('floors')->where('company_id', $this->company())->where('code', $code)->first(['id', 'name', 'code']) : null;
        $errors = [];
        if (! $floor) {
            $errors['floor'] = __('workstudy.ot.v.floor');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
            $errors['date'] = __('workstudy.ot.v.date');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [$floor, $date];
    }
}
