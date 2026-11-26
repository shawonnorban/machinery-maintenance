<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\WorkStudy\Models\WsChangeover;
use App\Modules\WorkStudy\Models\WsLineAllocation;
use App\Modules\WorkStudy\Models\WsNptEntry;
use App\Modules\WorkStudy\Models\WsSewingTarget;
use App\Modules\WorkStudy\Models\WsYarnTarget;
use App\Modules\WorkStudy\Resources\ChangeoverResource;
use App\Modules\WorkStudy\Support\WsCosting;
use App\Modules\WorkStudy\Support\WsTargets;
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
 * Work Study → Target & Feeding (AnnotechERP 14/03), as Brickland keeps it:
 *
 * - Excess Yarn Allowance → Excess Yarn Target: every department's allowance
 *   for one month (Copy From builds it from another month; a cleared cell is
 *   no allowance agreed, not an allowance of zero);
 * - Style Feeding & Change-over → Dashboard: change-overs by floor, line,
 *   buyer and style over a date range, ticked rows finalised, reopened or
 *   removed (a finalised one is not removed);
 * - Garment Target Setting → the target sheet (every line's orders on a day,
 *   a spare row on each, Start Copy from another day), Production Entry (a
 *   line's allocations), Production Plans (which are on target, how each
 *   stands) and NPT Entry (a line's stoppages on a day).
 *
 * A floor travels by its code, a line by its code, a month as 2026-10, a day
 * as 2026-10-25. The target sheet, the line's plans and its NPT print (PDF)
 * and export (Excel), as Brickland's do.
 */
class WsTargetApiController extends ApiController
{
    /** What the forms and sheets choose from. */
    public function options(): JsonResponse
    {
        $this->allow('workstudy.target.view');
        $floors = DB::table('floors')->where('company_id', $this->company())->orderBy('name')->orderBy('code')->get(['id', 'name', 'code']);
        $years = range((int) now()->format('Y') - 1, (int) now()->format('Y') + 1);

        return ApiResponse::ok([
            'company' => ['value' => $this->company(), 'label' => DB::table('companies')->where('id', $this->company())->value('name')],
            'years' => $years,
            'periods' => collect($years)->flatMap(fn ($y) => array_map(fn ($m) => sprintf('%04d-%02d', $y, $m), range(1, 12)))->values()->all(),
            'floors' => $floors->map(fn ($f) => ['value' => $f->id, 'code' => $f->code, 'label' => "{$f->name} — {$f->code}"])->values()->all(),
            'lines' => PlanningLine::query()->whereIn('floor_id', $floors->pluck('id'))->where('active', true)->orderBy('display_order')->orderBy('code')->get()
                ->map(fn (PlanningLine $l) => ['value' => $l->id, 'code' => $l->code, 'floor_id' => $l->floor_id, 'label' => ($floors->firstWhere('id', $l->floor_id)?->name ?? '').' — '.$l->code])->values()->all(),
            'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->get()->map(fn (Buyer $b) => ['value' => $b->id, 'label' => WsCosting::buyerLabel($b)])->values()->all(),
            'departments' => DB::table('departments')->where('company_id', $this->company())->orderBy('name')->get(['id', 'name'])->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->values()->all(),
            // An order as the W.Style/Job# boxes offer it: its number — its style.
            'orders' => Order::query()->whereNotIn('status', ['CANCELLED'])->orderByDesc('created_at')->limit(500)->get()
                ->map(fn (Order $o) => ['value' => $o->order_no, 'label' => trim($o->order_no.($o->style_no ? " — {$o->style_no}" : '')), 'buyer_id' => $o->buyer_id, 'item' => $o->item])->values()->all(),
            'reasons' => WsTargets::REASONS, 'npt_depts' => WsTargets::NPT_DEPTS, 'hour_slots' => WsTargets::HOUR_SLOTS, 'statuses' => WsTargets::STATUSES,
        ]);
    }

    /* ---------------- Excess Yarn Target ---------------- */

    public function yarn(Request $request): JsonResponse
    {
        $this->allow('workstudy.target.view');
        $period = $this->period($request->query('period'));
        $copy = $request->query('copy');
        $from = is_string($copy) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $copy) ? $copy : $period;

        return ApiResponse::ok(['period' => $period, 'copied_from' => $from === $period ? null : $from, 'rows' => $this->yarnRows($from)]);
    }

    /** Save Target: each department's allowance into the month chosen; a cleared cell drops it. */
    public function saveYarn(Request $request): JsonResponse
    {
        $this->allow('workstudy.target.manage');
        $data = $request->validate([
            'period' => ['required', 'string'],
            'rows' => ['present', 'array'],
            'rows.*.department_id' => ['required', Rule::exists('departments', 'id')->where('company_id', $this->company())],
            'rows.*.target_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);
        $period = $this->period($data['period']);
        DB::transaction(function () use ($data, $period): void {
            foreach ($data['rows'] as $r) {
                $row = WsYarnTarget::query()->where('period', $period)->where('department_id', $r['department_id'])->first();
                if (($r['target_qty'] ?? null) === null) {
                    $row?->delete();

                    continue;
                }
                $row ? $row->update(['target_qty' => $r['target_qty']]) : WsYarnTarget::query()->create(['company_id' => $this->company(), 'period' => $period, 'department_id' => $r['department_id'], 'target_qty' => $r['target_qty']]);
            }
        });

        return ApiResponse::ok(['period' => $period, 'copied_from' => null, 'rows' => $this->yarnRows($period)]);
    }

    /* ---------------- Style Changeover Dashboard ---------------- */

    public function changeovers(Request $request): JsonResponse
    {
        $this->allow('workstudy.target.view');
        $f = $request->validate([
            'floor' => ['nullable', 'string'], 'line' => ['nullable', 'string'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'buyer' => ['nullable', 'string'], 'order' => ['nullable', 'string'],
        ]);
        $floor = ! empty($f['floor']) ? DB::table('floors')->where('company_id', $this->company())->where('code', $f['floor'])->value('id') : null;
        $line = ! empty($f['line']) ? PlanningLine::query()->where('code', $f['line'])->when($floor, fn ($q) => $q->where('floor_id', $floor))->value('id') : null;
        $definition = app(ChangeoverResource::class);
        $rows = WsChangeover::query()
            ->when(! empty($f['floor']), fn ($q) => $q->where('floor_id', $floor))
            ->when(! empty($f['line']), fn ($q) => $q->where('planning_line_id', $line))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('changeover_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('changeover_date', '<=', $f['to']))
            ->when(! empty($f['buyer']), fn ($q) => $q->where('new_buyer_id', $f['buyer']))
            ->when(! empty($f['order']), fn ($q) => $q->where('new_order', $f['order']))
            ->orderBy('changeover_date')->orderBy('created_at')->get()
            ->map(fn (WsChangeover $c) => $definition->present($c))->values()->all();

        return ApiResponse::ok(['rows' => $rows]);
    }

    /** Finalize, Reopen or Remove the ticked change-overs; a finalised one is not removed. */
    public function changeoverAction(Request $request): JsonResponse
    {
        $this->allow('workstudy.target.manage');
        $data = $request->validate(['action' => ['required', Rule::in(['finalize', 'reopen', 'remove'])], 'ids' => ['required', 'array', 'min:1'], 'ids.*' => ['string']]);
        $rows = WsChangeover::query()->whereIn('id', $data['ids'])->get();
        if ($data['action'] === 'remove' && $rows->contains('finalized', true)) {
            throw ValidationException::withMessages(['ids' => __('workstudy.tf.v.finalized')]);
        }
        DB::transaction(fn () => match ($data['action']) {
            'finalize' => $rows->each->update(['finalized' => true]),
            'reopen' => $rows->each->update(['finalized' => false]),
            'remove' => $rows->each->delete(),
        });

        return ApiResponse::ok(['count' => $rows->count()]);
    }

    /* ---------------- Sewing Target Setting ---------------- */

    public function targets(Request $request): JsonResponse
    {
        $this->allow('workstudy.target.view');
        [$floor, $date] = $this->day($request);

        return ApiResponse::ok(['rows' => WsTargets::sheet($floor->id, $date)]);
    }

    /** Save the target sheet: Target[Gen Hr] worked out for every row; a row left blank writes nothing, a saved row cleared is removed. */
    public function saveTargets(Request $request): JsonResponse
    {
        $this->allow('workstudy.target.manage');
        [$floor, $date] = $this->day($request);
        $count = ['nullable', 'integer', 'min:0', 'max:9999999'];
        $num = ['nullable', 'numeric', 'min:0', 'max:99999999'];
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['nullable', 'string'],
            'rows.*.planning_line_id' => ['required', Rule::exists('planning_lines', 'id')->where('company_id', $this->company())->where('floor_id', $floor->id)],
            'rows.*.buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $this->company())],
            'rows.*.order_style' => ['nullable', 'string', 'max:64'], 'rows.*.gmt_item' => ['nullable', 'string', 'max:120'], 'rows.*.ship_date' => ['nullable', 'date'],
            'rows.*.fob' => $num, 'rows.*.smv' => ['nullable', 'numeric', 'min:0', 'max:999'], 'rows.*.day_run' => $count, 'rows.*.lo_effi' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rows.*.t_effi' => ['nullable', 'numeric', 'min:0', 'max:100'], 'rows.*.wh' => ['nullable', 'numeric', 'min:0', 'max:24'], 'rows.*.wh_from' => ['nullable', 'numeric', 'min:0', 'max:24'], 'rows.*.wh_to' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'rows.*.used_op' => $count, 'rows.*.used_aso' => $count, 'rows.*.layout_op' => $count, 'rows.*.layout_aso' => $count, 'rows.*.wip_rfi' => $count, 'rows.*.plan' => $count,
            'rows.*.target_ot' => $count, 'rows.*.prod_gen' => $count, 'rows.*.prod_ot' => $count, 'rows.*.input_min' => $count, 'rows.*.prod_min' => $count, 'rows.*.ttl_value' => $num,
            'rows.*.is_car' => ['nullable', 'boolean'], 'rows.*.is_merged' => ['nullable', 'boolean'], 'rows.*.is_single' => ['nullable', 'boolean'], 'rows.*.is_off' => ['nullable', 'boolean'],
        ]);
        ksort($data['rows']);
        $lines = PlanningLine::query()->where('floor_id', $floor->id)->pluck('code', 'id');
        $errors = [];
        foreach ($data['rows'] as $i => $r) {
            if (! $this->blankTarget($r) && (float) ($r['smv'] ?? 0) <= 0) {
                $errors["rows.{$i}.smv"] = __('workstudy.tf.v.smv_row', ['line' => $lines[$r['planning_line_id']] ?? '']);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($data, $floor, $date): void {
            $saved = WsSewingTarget::query()->where('floor_id', $floor->id)->whereDate('target_date', $date)->get()->keyBy('id');
            $sort = [];
            foreach ($data['rows'] as $r) {
                $row = ! empty($r['id']) ? $saved->get($r['id']) : null;
                if ($this->blankTarget($r)) {
                    $row?->delete();

                    continue;
                }
                $values = collect($r)->except(['id', 'planning_line_id'])->all();
                foreach (['is_car', 'is_merged', 'is_single', 'is_off'] as $flag) {
                    $values[$flag] = (bool) ($values[$flag] ?? false);
                }
                $values['used_op'] = (int) ($values['used_op'] ?? 0);
                $values['used_aso'] = (int) ($values['used_aso'] ?? 0);
                $values['t_effi'] = (float) ($values['t_effi'] ?? 0);
                $values['wh'] = (float) ($values['wh'] ?? 0);
                $values['target_gen'] = WsTargets::targetGen($values['used_op'], $values['used_aso'], $values['wh'], $values['t_effi'], (float) $values['smv']);
                $values['sort_order'] = $sort[$r['planning_line_id']] = ($sort[$r['planning_line_id']] ?? 0) + 1;
                $row ? $row->update($values) : WsSewingTarget::query()->create($values + [
                    'company_id' => $this->company(), 'target_date' => $date, 'floor_id' => $floor->id, 'planning_line_id' => $r['planning_line_id'], 'created_by' => $this->caller()->auditUserId(),
                ]);
            }
        });

        return ApiResponse::ok(['rows' => WsTargets::sheet($floor->id, $date)]);
    }

    /** Start Copy: another day's setup onto this one — order, SMV, manpower, hours and efficiency, with production at nothing — on lines with no order today. */
    public function copyTargets(Request $request): JsonResponse
    {
        $this->allow('workstudy.target.manage');
        [$floor, $date] = $this->day($request);
        $from = (string) $request->input('from', '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || $from === $date) {
            throw ValidationException::withMessages(['from' => __('workstudy.tf.v.copy_from')]);
        }
        $busy = WsSewingTarget::query()->where('floor_id', $floor->id)->whereDate('target_date', $date)->pluck('planning_line_id')->unique()->flip();
        $copied = 0;
        DB::transaction(function () use ($floor, $date, $from, $busy, &$copied): void {
            foreach (WsSewingTarget::query()->where('floor_id', $floor->id)->whereDate('target_date', $from)->orderBy('sort_order')->get() as $t) {
                if ($busy->has($t->planning_line_id)) {
                    continue;
                }
                $copy = $t->replicate(['prod_gen', 'prod_ot', 'input_min', 'prod_min', 'ttl_value', 'target_ot', 'is_off']);
                $copy->target_date = $date;
                $copy->day_run = $t->day_run !== null ? $t->day_run + 1 : null;
                $copy->created_by = $this->caller()->auditUserId();
                $copy->save();
                $copied++;
            }
        });

        return ApiResponse::ok(['copied' => $copied, 'rows' => WsTargets::sheet($floor->id, $date)]);
    }

    /* ---------------- Production Entry / Production Plans ---------------- */

    public function allocations(Request $request): JsonResponse
    {
        $this->allow('workstudy.target.view');
        $line = $this->line($request);

        return ApiResponse::ok(['rows' => WsTargets::allocations($line->id, $request->query('view') !== 'plans')]);
    }

    /** Production Entry's Save: allocations as typed; a new row needs an order and an allocation; a row cleared is removed. */
    public function saveAllocations(Request $request): JsonResponse
    {
        $this->allow('workstudy.target.manage');
        $line = $this->line($request);
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['nullable', 'string'],
            'rows.*.buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $this->company())],
            'rows.*.order_style' => ['nullable', 'string', 'max:64'], 'rows.*.style' => ['nullable', 'string', 'max:64'], 'rows.*.item' => ['nullable', 'string', 'max:120'],
            'rows.*.smv' => ['nullable', 'numeric', 'min:0', 'max:999'], 'rows.*.input_date' => ['nullable', 'date'], 'rows.*.plan_start' => ['nullable', 'date'], 'rows.*.plan_end' => ['nullable', 'date'],
            'rows.*.allocation' => ['nullable', 'integer', 'min:0', 'max:99999999'], 'rows.*.production' => ['nullable', 'integer', 'min:0', 'max:99999999'],
        ]);
        ksort($data['rows']);
        $errors = [];
        foreach ($data['rows'] as $i => $r) {
            $blank = trim((string) ($r['order_style'] ?? '')) === '' && empty($r['allocation']) && empty($r['production']);
            if ($blank) {
                continue;
            }
            if (trim((string) ($r['order_style'] ?? '')) === '') {
                $errors["rows.{$i}.order_style"] = __('workstudy.tf.v.alloc_order', ['n' => $i + 1]);
            } elseif (! empty($r['plan_start']) && ! empty($r['plan_end']) && $r['plan_end'] < $r['plan_start']) {
                $errors["rows.{$i}.plan_end"] = __('workstudy.tf.v.plan_end', ['n' => $i + 1]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($data, $line): void {
            $saved = WsLineAllocation::query()->where('planning_line_id', $line->id)->get()->keyBy('id');
            foreach ($data['rows'] as $r) {
                $row = ! empty($r['id']) ? $saved->get($r['id']) : null;
                if (trim((string) ($r['order_style'] ?? '')) === '' && empty($r['allocation']) && empty($r['production'])) {
                    $row?->delete();

                    continue;
                }
                $values = collect($r)->only(['buyer_id', 'order_style', 'style', 'item', 'smv', 'input_date', 'plan_start', 'plan_end'])->all()
                    + ['allocation' => (int) ($r['allocation'] ?? 0), 'production' => (int) ($r['production'] ?? 0)];
                $row ? $row->update($values) : WsLineAllocation::query()->create($values + ['company_id' => $this->company(), 'floor_id' => $line->floor_id, 'planning_line_id' => $line->id]);
            }
        });

        return ApiResponse::ok(['rows' => WsTargets::allocations($line->id)]);
    }

    /** Production Plans' Save: which plans are on target, and each one's status; a closed plan comes off the target. */
    public function savePlans(Request $request): JsonResponse
    {
        $this->allow('workstudy.target.manage');
        $line = $this->line($request);
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['required', 'string'],
            'rows.*.in_target' => ['boolean'],
            'rows.*.status' => ['required', Rule::in(WsTargets::STATUSES)],
        ]);
        DB::transaction(function () use ($data, $line): void {
            $saved = WsLineAllocation::query()->where('planning_line_id', $line->id)->get()->keyBy('id');
            foreach ($data['rows'] as $r) {
                $saved->get($r['id'])?->update(['status' => $r['status'], 'in_target' => $r['status'] === 'CLOSED' ? false : (bool) ($r['in_target'] ?? false)]);
            }
        });

        return ApiResponse::ok(['rows' => WsTargets::allocations($line->id, false)]);
    }

    /* ---------------- NPT Entry ---------------- */

    public function npt(Request $request): JsonResponse
    {
        $this->allow('workstudy.target.view');
        $line = $this->line($request);
        $date = $this->date($request);

        return ApiResponse::ok(['rows' => WsTargets::npt($line->id, $date)]);
    }

    /** Save NPT: manpower × -Min worked out; a row left blank writes nothing; a saved row cleared is removed. */
    public function saveNpt(Request $request): JsonResponse
    {
        $this->allow('workstudy.target.manage');
        $line = $this->line($request);
        $date = $this->date($request);
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['nullable', 'string'],
            'rows.*.order_style' => ['nullable', 'string', 'max:64'],
            'rows.*.reason_type' => ['nullable', Rule::in(WsTargets::REASONS)],
            'rows.*.reason_details' => ['nullable', 'string', 'max:255'],
            'rows.*.responsible_dept' => ['nullable', Rule::in(WsTargets::NPT_DEPTS)],
            'rows.*.hour_slots' => ['nullable', 'array'], 'rows.*.hour_slots.*' => ['integer', Rule::in(WsTargets::HOUR_SLOTS)],
            'rows.*.manpower' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'rows.*.minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
        ]);
        ksort($data['rows']);
        $blank = fn (array $r) => empty($r['reason_type']) && empty($r['manpower']) && empty($r['minutes']) && trim((string) ($r['order_style'] ?? '')) === '' && empty($r['hour_slots']);
        $errors = [];
        foreach ($data['rows'] as $i => $r) {
            if ($blank($r)) {
                continue;
            }
            foreach (['reason_type' => 'npt_reason', 'manpower' => 'npt_manpower', 'minutes' => 'npt_minutes'] as $k => $msg) {
                if (empty($r[$k])) {
                    $errors["rows.{$i}.{$k}"] = __("workstudy.tf.v.{$msg}", ['n' => $i + 1]);
                }
            }
            if (empty($r['hour_slots'])) {
                $errors["rows.{$i}.hour_slots"] = __('workstudy.tf.v.npt_slots', ['n' => $i + 1]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($data, $line, $date, $blank): void {
            $saved = WsNptEntry::query()->where('planning_line_id', $line->id)->whereDate('npt_date', $date)->get()->keyBy('id');
            foreach ($data['rows'] as $r) {
                $row = ! empty($r['id']) ? $saved->get($r['id']) : null;
                if ($blank($r)) {
                    $row?->delete();

                    continue;
                }
                $slots = collect($r['hour_slots'])->map(fn ($s) => (int) $s)->unique()->sort()->values()->all();
                $values = ['order_style' => $r['order_style'] ?? null, 'reason_type' => $r['reason_type'], 'reason_details' => $r['reason_details'] ?? null, 'responsible_dept' => $r['responsible_dept'] ?? null,
                    'hour_slots' => $slots, 'manpower' => (int) $r['manpower'], 'minutes' => (int) $r['minutes'], 'npt' => (int) $r['manpower'] * (int) $r['minutes']];
                $row ? $row->update($values) : WsNptEntry::query()->create($values + ['company_id' => $this->company(), 'npt_date' => $date, 'floor_id' => $line->floor_id, 'planning_line_id' => $line->id]);
            }
        });

        return ApiResponse::ok(['rows' => WsTargets::npt($line->id, $date)]);
    }

    /* ---------------- PDF and Excel ---------------- */

    /** The target sheet, a line's entry or plans, or its NPT as a PDF or a workbook. */
    public function export(Request $request, string $sheet, string $format): Response|StreamedResponse
    {
        $this->allow('workstudy.target.view');
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        [$title, $columns, $rows, $meta, $total, $name] = match ($sheet) {
            'targets' => $this->targetsTable($request),
            'entry', 'plans' => $this->allocationsTable($request, $sheet),
            'npt' => $this->nptTable($request),
            default => abort(404),
        };
        if ($format === 'pdf') {
            $subtitle = DB::table('companies')->where('id', $this->company())->value('name').' · '.trans_choice('workstudy.cost.rows', count($rows), ['count' => count($rows)]);

            return response((new TablePdf($title, $columns, $rows, $meta, $total, $subtitle))->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'.pdf"']);
        }

        return response()->streamDownload(function () use ($columns, $rows, $total): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn ($c) => $c['label'], $columns)));
            foreach ([...$rows, ...($total ? [$total] : [])] as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($v) => $v ?? '', $r)));
            }
            $writer->close();
        }, $name.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return array{0: string, 1: list<array{label: string, numeric?: bool}>, 2: list<list<mixed>>, 3: array<string, string>, 4: ?list<mixed>, 5: string} */
    private function targetsTable(Request $request): array
    {
        [$floor, $date] = $this->day($request);
        $buyers = Buyer::query()->pluck('short_name', 'id');
        $keys = ['line', 'buyer', 'order_style', 'gmt_item', 'smv', 't_effi', 'wh', 'used_op', 'used_aso', 'layout_op', 'layout_aso', 'wip_rfi', 'target_gen', 'target_ot', 'ttl_target', 'prod_gen', 'prod_ot', 'ttl_prod'];
        $text = ['line', 'buyer', 'order_style', 'gmt_item'];
        $columns = array_map(fn ($k) => ['label' => __("workstudy.tf.sheet.col.{$k}"), 'numeric' => ! in_array($k, $text, true)], $keys);
        $rows = [];
        foreach (WsTargets::sheet($floor->id, $date) as $r) {
            if ($r['spare']) {
                continue;
            }
            $r['buyer'] = $buyers[$r['buyer_id']] ?? null;
            $rows[] = array_map(fn ($k) => $r[$k] ?? null, $keys);
        }
        $sum = fn (string $k) => array_sum(array_map(fn ($r) => (float) $r[array_search($k, $keys, true)], $rows));
        $total = $rows ? array_map(fn ($k) => match ($k) {
            'line' => __('workstudy.cost.total'),
            'used_op', 'used_aso', 'target_gen', 'target_ot', 'ttl_target', 'prod_gen', 'prod_ot', 'ttl_prod' => $sum($k),
            default => null,
        }, $keys) : null;

        return [__('workstudy.tf.sheet.title'), $columns, $rows, [__('workstudy.cost.day.floor') => "{$floor->name} — {$floor->code}", __('workstudy.cost.day.date') => $date], $total, "sewing-targets-{$floor->code}-{$date}"];
    }

    /** @return array{0: string, 1: list<array{label: string, numeric?: bool}>, 2: list<list<mixed>>, 3: array<string, string>, 4: ?list<mixed>, 5: string} */
    private function allocationsTable(Request $request, string $sheet): array
    {
        $line = $this->line($request);
        $buyers = Buyer::query()->pluck('short_name', 'id');
        $keys = ['buyer', 'order_style', 'style', 'item', 'smv', 'plan_start', 'plan_end', 'allocation', 'production', 'achieved_pct', 'remaining', 'status'];
        $text = ['buyer', 'order_style', 'style', 'item', 'plan_start', 'plan_end', 'status'];
        $columns = array_map(fn ($k) => ['label' => __("workstudy.tf.alloc.col.{$k}"), 'numeric' => ! in_array($k, $text, true)], $keys);
        $rows = array_map(fn ($r) => array_map(fn ($k) => match ($k) {
            'buyer' => $buyers[$r['buyer_id']] ?? null,
            'status' => __("workstudy.tf.status.{$r['status']}"),
            default => $r[$k] ?? null,
        }, $keys), WsTargets::allocations($line->id, false));
        $floor = DB::table('floors')->where('id', $line->floor_id)->first(['name', 'code']);

        return [__("workstudy.tf.{$sheet}.title"), $columns, $rows, [__('workstudy.cost.day.floor') => "{$floor->name} — {$floor->code}", __('workstudy.tf.line') => $line->code], null, "{$sheet}-{$line->code}"];
    }

    /** @return array{0: string, 1: list<array{label: string, numeric?: bool}>, 2: list<list<mixed>>, 3: array<string, string>, 4: ?list<mixed>, 5: string} */
    private function nptTable(Request $request): array
    {
        $line = $this->line($request);
        $date = $this->date($request);
        $keys = ['order_style', 'reason_type', 'reason_details', 'responsible_dept', 'hour_slots', 'manpower', 'minutes', 'npt'];
        $columns = array_map(fn ($k) => ['label' => __("workstudy.tf.npt.col.{$k}"), 'numeric' => in_array($k, ['manpower', 'minutes', 'npt'], true)], $keys);
        $rows = array_map(fn ($r) => array_map(fn ($k) => $k === 'hour_slots' ? implode(', ', $r[$k]) : ($r[$k] ?? null), $keys), array_values(array_filter(WsTargets::npt($line->id, $date), fn ($r) => $r['id'])));
        $total = $rows ? array_map(fn ($k) => match ($k) {
            'order_style' => __('workstudy.cost.total'), 'npt' => array_sum(array_column($rows, 7)), default => null
        }, $keys) : null;

        return [__('workstudy.tf.npt.title'), $columns, $rows, [__('workstudy.tf.line') => $line->code, __('workstudy.cost.day.date') => $date], $total, "npt-{$line->code}-{$date}"];
    }

    /* ---------------- helpers ---------------- */

    private function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /** @param array<string, mixed> $r */
    private function blankTarget(array $r): bool
    {
        return trim((string) ($r['order_style'] ?? '')) === '' && empty($r['used_op']) && empty($r['used_aso']) && empty($r['smv']) && empty($r['prod_gen']) && empty($r['prod_ot']);
    }

    /** @return list<array{department_id: string, department: string, target_qty: ?float}> */
    private function yarnRows(string $period): array
    {
        $saved = WsYarnTarget::query()->where('period', $period)->pluck('target_qty', 'department_id');

        return DB::table('departments')->where('company_id', $this->company())->orderBy('name')->get(['id', 'name'])
            ->map(fn ($d) => ['department_id' => $d->id, 'department' => $d->name, 'target_qty' => isset($saved[$d->id]) ? (float) $saved[$d->id] : null])->values()->all();
    }

    private function period(mixed $period): string
    {
        if (! is_string($period) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw ValidationException::withMessages(['period' => __('workstudy.cost.v.period')]);
        }

        return $period;
    }

    private function date(Request $request): string
    {
        $date = (string) $request->query('date', '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
            throw ValidationException::withMessages(['date' => __('workstudy.cost.v.date')]);
        }

        return Carbon::parse($date)->toDateString();
    }

    /** @return array{0: object, 1: string} */
    private function day(Request $request): array
    {
        $code = (string) $request->query('floor', '');
        $floor = $code !== '' ? DB::table('floors')->where('company_id', $this->company())->where('code', $code)->first(['id', 'name', 'code']) : null;
        if (! $floor) {
            throw ValidationException::withMessages(['floor' => __('workstudy.cost.v.floor')]);
        }

        return [$floor, $this->date($request)];
    }

    /** A line by its floor's code and its own (?floor=A-1F&line=LINE01). */
    private function line(Request $request): PlanningLine
    {
        $floor = DB::table('floors')->where('company_id', $this->company())->where('code', (string) $request->query('floor', ''))->value('id');
        $line = $floor ? PlanningLine::query()->where('floor_id', $floor)->where('code', (string) $request->query('line', ''))->first() : null;
        if (! $line) {
            throw ValidationException::withMessages([$floor ? 'line' : 'floor' => $floor ? __('workstudy.tf.v.line') : __('workstudy.cost.v.floor')]);
        }

        return $line;
    }
}
