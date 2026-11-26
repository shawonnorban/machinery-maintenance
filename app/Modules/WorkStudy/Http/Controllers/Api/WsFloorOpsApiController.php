<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\WorkStudy\Models\WsManMinuteAdjustment;
use App\Modules\WorkStudy\Models\WsOutputAdjustment;
use App\Modules\WorkStudy\Models\WsWashRequisition;
use App\Modules\WorkStudy\Resources\WashRequisitionResource;
use App\Modules\WorkStudy\Support\WsCosting;
use App\Modules\WorkStudy\Support\WsFloorOps;
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
 * Work Study → Floor Operations (AnnotechERP 14/07), as Brickland keeps it:
 *
 * - Sewing Output Adjustment → the hour sheet: every line on a floor for one
 *   hour of a day, the recorded output beside what it is corrected to. A line
 *   left at the recorded figure writes nothing; one corrected back to it
 *   loses its adjustment.
 * - Garment Wash Requisition → the list: requisitions by step, buyer, number,
 *   style, order and date, each moved one step by Take Action and Apply; a
 *   complete one offers no action.
 * - Standard Minute Adjustment → a line's sheet for a day: every reason its
 *   people were not sewing its order, the general shift and each overtime
 *   hour, Man OT added up and never typed. A reason left at nothing writes
 *   nothing.
 *
 * A floor travels by its code, a line by its code, a day as 2026-10-25, an
 * hour as 1 … 11. Each sheet prints (PDF) and exports (Excel), as Brickland's do.
 */
class WsFloorOpsApiController extends ApiController
{
    /** What the forms and sheets choose from. */
    public function options(): JsonResponse
    {
        $this->allow('workstudy.floorops.view');
        $floors = DB::table('floors')->where('company_id', $this->company())->orderBy('name')->orderBy('code')->get(['id', 'name', 'code']);

        return ApiResponse::ok([
            'floors' => $floors->map(fn ($f) => ['value' => $f->id, 'code' => $f->code, 'label' => "{$f->name} — {$f->code}"])->values()->all(),
            'lines' => PlanningLine::query()->whereIn('floor_id', $floors->pluck('id'))->where('active', true)->orderBy('display_order')->orderBy('code')->get()
                ->map(fn (PlanningLine $l) => ['value' => $l->id, 'code' => $l->code, 'floor_id' => $l->floor_id, 'label' => ($floors->firstWhere('id', $l->floor_id)?->name ?? '').' — '.$l->code])->values()->all(),
            'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->get()->map(fn (Buyer $b) => ['value' => $b->id, 'label' => WsCosting::buyerLabel($b)])->values()->all(),
            // Requisition By: an employee as Brickland names one — A.N.M. Salah Uddin (50004061).
            'employees' => DB::table('hr_employees')->where('company_id', $this->company())->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'employee_code'])
                ->map(fn ($e) => ['value' => $e->id, 'label' => trim($e->name.($e->employee_code ? " ({$e->employee_code})" : ''))])->values()->all(),
            // Attn To: a user of the company — AKM Nuruzzaman (mm@bcl.com).
            'users' => DB::table('company_users')->join('users', 'users.id', '=', 'company_users.user_id')->where('company_users.company_id', $this->company())->where('company_users.status', 'ACTIVE')
                ->orderBy('users.name')->get(['users.id', 'users.name', 'users.email'])->map(fn ($u) => ['value' => $u->id, 'label' => "{$u->name} ({$u->email})"])->values()->all(),
            'hours' => WsFloorOps::HOURS,
            'reasons' => WsFloorOps::MM_REASONS,
            'slots' => array_map(fn (int $s) => ['value' => (string) $s, 'label' => WsOvertime::slotLabel($s)], array_keys(WsOvertime::SLOTS)),
            'wash_statuses' => WsFloorOps::WASH_STATUSES,
            'wash_actions' => array_keys(WsFloorOps::WASH_ACTIONS),
        ]);
    }

    /* ---------------- Sewing Prod Adjustment ---------------- */

    public function output(Request $request): JsonResponse
    {
        $this->allow('workstudy.floorops.view');
        [$floor, $date, $hour] = $this->hourOf($request);

        return ApiResponse::ok(['rows' => WsFloorOps::outputSheet($floor->id, $date, $hour)]);
    }

    /** Save: each line's hour as corrected; one left at (or put back to) the recorded figure has no adjustment. */
    public function saveOutput(Request $request): JsonResponse
    {
        $this->allow('workstudy.floorops.manage');
        [$floor, $date, $hour] = $this->hourOf($request);
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.planning_line_id' => ['required', Rule::exists('planning_lines', 'id')->where('company_id', $this->company())->where('floor_id', $floor->id)],
            'rows.*.buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $this->company())],
            'rows.*.order_no' => ['nullable', 'string', 'max:64'],
            'rows.*.item_name' => ['nullable', 'string', 'max:120'],
            'rows.*.prod' => ['nullable', 'integer', 'min:0', 'max:9999999'],
        ]);
        DB::transaction(function () use ($data, $floor, $date, $hour): void {
            $saved = WsOutputAdjustment::query()->where('floor_id', $floor->id)->whereDate('adjust_date', $date)->where('hour', $hour)->whereNotNull('planning_line_id')->get()->keyBy('planning_line_id');
            foreach ($data['rows'] as $r) {
                $row = $saved->get($r['planning_line_id']);
                $recorded = $row ? $row->recorded_qty : WsFloorOps::recorded($r['planning_line_id'], $date, $hour);
                $prod = ($r['prod'] ?? null) === null ? $recorded : (int) $r['prod'];
                if ($prod === $recorded) {
                    $row?->delete();

                    continue;
                }
                $values = ['buyer_id' => $r['buyer_id'] ?? null, 'order_no' => $r['order_no'] ?? null, 'item_name' => $r['item_name'] ?? null, 'adjusted_qty' => $prod];
                $row ? $row->update($values) : WsOutputAdjustment::query()->create($values + [
                    'company_id' => $this->company(), 'adjust_date' => $date, 'floor_id' => $floor->id, 'planning_line_id' => $r['planning_line_id'], 'hour' => $hour,
                    'recorded_qty' => $recorded, 'created_by' => $this->caller()->auditUserId(),
                ]);
            }
        });

        return ApiResponse::ok(['rows' => WsFloorOps::outputSheet($floor->id, $date, $hour)]);
    }

    /* ---------------- Man Minutes Adjustment ---------------- */

    public function minutes(Request $request): JsonResponse
    {
        $this->allow('workstudy.floorops.view');
        $line = $this->line($request);

        return ApiResponse::ok(['rows' => WsFloorOps::manMinuteSheet($line->id, $this->date($request))]);
    }

    /** Save: every reason's general-shift and overtime people; Man OT added up; a reason left at nothing has no adjustment. */
    public function saveMinutes(Request $request): JsonResponse
    {
        $this->allow('workstudy.floorops.manage');
        $line = $this->line($request);
        $date = $this->date($request);
        $count = ['nullable', 'integer', 'min:0', 'max:9999'];
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.reason' => ['required', Rule::in(WsFloorOps::MM_REASONS)],
            'rows.*.used_op' => $count, 'rows.*.used_aso' => $count,
            'rows.*.slots' => ['nullable', 'array'], 'rows.*.slots.*.op' => $count, 'rows.*.slots.*.aso' => $count,
        ]);
        DB::transaction(function () use ($data, $line, $date): void {
            $saved = WsManMinuteAdjustment::query()->where('planning_line_id', $line->id)->whereDate('adjust_date', $date)->get()->keyBy('reason');
            foreach ($data['rows'] as $r) {
                $row = $saved->get($r['reason']);
                [$slots, $otOp, $otAso] = WsFloorOps::otTotals($r['slots'] ?? []);
                $values = ['used_op' => (int) ($r['used_op'] ?? 0), 'used_aso' => (int) ($r['used_aso'] ?? 0), 'ot_slots' => $slots ?: null, 'ot_op' => $otOp, 'ot_aso' => $otAso];
                if ($values['used_op'] + $values['used_aso'] + $otOp + $otAso === 0) {
                    $row?->delete();

                    continue;
                }
                $row ? $row->update($values) : WsManMinuteAdjustment::query()->create($values + [
                    'company_id' => $this->company(), 'adjust_date' => $date, 'floor_id' => $line->floor_id, 'planning_line_id' => $line->id, 'reason' => $r['reason'],
                    'created_by' => $this->caller()->auditUserId(),
                ]);
            }
        });

        return ApiResponse::ok(['rows' => WsFloorOps::manMinuteSheet($line->id, $date)]);
    }

    /* ---------------- Wash Requisition List ---------------- */

    public function wash(Request $request): JsonResponse
    {
        $this->allow('workstudy.floorops.view');
        $rows = $this->washRows($request);

        return ApiResponse::ok(['rows' => $rows, 'open' => count(array_filter($rows, fn ($r) => in_array($r['status'], WsFloorOps::WASH_OPEN, true)))]);
    }

    /** Apply: each requisition given an action moves to that action's step; a complete one has none to take. */
    public function washAction(Request $request): JsonResponse
    {
        $this->allow('workstudy.floorops.manage');
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.id' => ['required', 'string'],
            'rows.*.action' => ['required', Rule::in(array_keys(WsFloorOps::WASH_ACTIONS))],
        ]);
        $saved = WsWashRequisition::query()->whereIn('id', array_column($data['rows'], 'id'))->get()->keyBy('id');
        foreach ($data['rows'] as $i => $r) {
            $row = $saved->get($r['id']);
            if (! $row) {
                throw ValidationException::withMessages(["rows.{$i}.id" => __('workstudy.fo.v.wash_missing')]);
            }
            if ($row->status === 'COMPLETE') {
                throw ValidationException::withMessages(["rows.{$i}.action" => __('workstudy.fo.v.wash_complete', ['no' => $row->requisition_no])]);
            }
        }
        DB::transaction(function () use ($data, $saved): void {
            foreach ($data['rows'] as $r) {
                $saved->get($r['id'])->update(['status' => WsFloorOps::WASH_ACTIONS[$r['action']]]);
            }
        });

        return ApiResponse::ok(['count' => count($data['rows'])]);
    }

    /* ---------------- PDF and Excel ---------------- */

    /** The hour sheet, a line's man-minute sheet or the wash requisition list as a PDF or a workbook. */
    public function export(Request $request, string $sheet, string $format): Response|StreamedResponse
    {
        $this->allow('workstudy.floorops.view');
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        [$title, $columns, $rows, $meta, $total, $name] = match ($sheet) {
            'output' => $this->outputTable($request),
            'minutes' => $this->minutesTable($request),
            'wash' => $this->washTable($request),
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
    private function outputTable(Request $request): array
    {
        [$floor, $date, $hour] = $this->hourOf($request);
        $buyers = Buyer::query()->pluck('short_name', 'id');
        $keys = ['line', 'buyer', 'order_no', 'item_name', 'recorded', 'prod', 'diff'];
        $columns = array_map(fn ($k) => ['label' => __("workstudy.fo.output.col.{$k}"), 'numeric' => in_array($k, ['recorded', 'prod', 'diff'], true)], $keys);
        $rows = array_map(fn ($r) => [$r['line'], $buyers[$r['buyer_id']] ?? null, $r['order_no'], $r['item_name'], $r['recorded'], $r['prod'], $r['prod'] - $r['recorded']], WsFloorOps::outputSheet($floor->id, $date, $hour));
        $total = $rows ? [__('workstudy.cost.total'), null, null, null, array_sum(array_column($rows, 4)), array_sum(array_column($rows, 5)), array_sum(array_column($rows, 6))] : null;

        return [__('workstudy.fo.output.title'), $columns, $rows, [__('workstudy.cost.day.floor') => "{$floor->name} — {$floor->code}", __('workstudy.cost.day.date') => $date, __('workstudy.fo.output.hour') => (string) $hour], $total, "output-adjustment-{$floor->code}-{$date}-h{$hour}"];
    }

    /** @return array{0: string, 1: list<array{label: string, numeric?: bool}>, 2: list<list<mixed>>, 3: array<string, string>, 4: ?list<mixed>, 5: string} */
    private function minutesTable(Request $request): array
    {
        $line = $this->line($request);
        $date = $this->date($request);
        $slots = array_keys(WsOvertime::SLOTS);
        $columns = [
            ['label' => __('workstudy.fo.mm.col.reason'), 'nowrap' => true],
            ...array_map(fn ($k) => ['label' => __("workstudy.fo.mm.col.{$k}"), 'numeric' => true], ['used_op', 'used_aso', 'ot_op', 'ot_aso']),
            ...collect($slots)->flatMap(fn ($s) => [['label' => WsOvertime::slotLabel($s).' OP', 'numeric' => true], ['label' => WsOvertime::slotLabel($s).' ASO', 'numeric' => true]])->all(),
        ];
        // The reasons someone was booked away for: a sheet of fifteen zero rows prints nothing.
        $rows = [];
        foreach (WsFloorOps::manMinuteSheet($line->id, $date) as $r) {
            if (! $r['id']) {
                continue;
            }
            $rows[] = [$r['reason'], $r['used_op'], $r['used_aso'], $r['ot_op'], $r['ot_aso'], ...collect($slots)->flatMap(fn ($s) => [$r['slots'][(string) $s]['op'] ?: null, $r['slots'][(string) $s]['aso'] ?: null])->all()];
        }
        $total = $rows ? [__('workstudy.cost.total'), ...array_map(fn ($i) => array_sum(array_map(fn ($r) => (int) $r[$i], $rows)), range(1, 4 + 2 * count($slots)))] : null;
        $floor = DB::table('floors')->where('id', $line->floor_id)->first(['name', 'code']);

        return [__('workstudy.fo.mm.title'), $columns, $rows, [__('workstudy.cost.day.floor') => "{$floor->name} — {$floor->code}", __('workstudy.fo.line') => $line->code, __('workstudy.cost.day.date') => $date], $total, "man-minutes-{$line->code}-{$date}"];
    }

    /** @return array{0: string, 1: list<array{label: string, numeric?: bool}>, 2: list<list<mixed>>, 3: array<string, string>, 4: ?list<mixed>, 5: string} */
    private function washTable(Request $request): array
    {
        $keys = ['requisition_no', 'requisition_date', 'buyer', 'style_no', 'order_no', 'shipment_date', 'order_qty', 'wash_qty', 'start_date', 'close_date', 'remarks', 'status'];
        $columns = array_map(fn ($k) => ['label' => __("workstudy.fo.wash.col.{$k}"), 'numeric' => in_array($k, ['order_qty', 'wash_qty'], true)], $keys);
        $rows = array_map(fn ($r) => array_map(fn ($k) => $k === 'status' ? __("workstudy.fo.wash.status.{$r['status']}") : ($r[$k] ?? null), $keys), $this->washRows($request));
        $status = (string) $request->query('status', 'OPEN');
        $meta = [__('workstudy.fo.wash.filter.status') => match ($status) {
            'ALL' => __('workstudy.fo.wash.filter.all'), 'OPEN' => __('workstudy.fo.wash.filter.open'), default => __("workstudy.fo.wash.status.{$status}"),
        }];
        foreach (['from' => 'date_from', 'to' => 'date_to'] as $q => $label) {
            if ($request->filled($q)) {
                $meta[__("workstudy.fo.wash.filter.{$label}")] = (string) $request->query($q);
            }
        }
        $total = $rows ? [__('workstudy.cost.total'), null, null, null, null, null, array_sum(array_map(fn ($r) => (int) $r[6], $rows)), array_sum(array_map(fn ($r) => (int) $r[7], $rows)), null, null, null, null] : null;

        return [__('workstudy.fo.wash.title'), $columns, $rows, $meta, $total, 'wash-requisitions'];
    }

    /* ---------------- helpers ---------------- */

    private function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /**
     * The list as searched: Still open (the default) is every requisition
     * neither complete nor cancelled; ALL is every one.
     *
     * @return list<array<string, mixed>>
     */
    private function washRows(Request $request): array
    {
        $f = $request->validate([
            'status' => ['nullable', Rule::in(['OPEN', 'ALL', ...WsFloorOps::WASH_STATUSES])],
            'buyer' => ['nullable', 'string'], 'no' => ['nullable', 'string', 'max:64'], 'style' => ['nullable', 'string', 'max:64'], 'order' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $status = $f['status'] ?? 'OPEN';
        $like = fn (string $v) => '%'.addcslashes(trim($v), '%_\\').'%';
        $definition = app(WashRequisitionResource::class);

        return WsWashRequisition::query()
            ->when($status === 'OPEN', fn ($q) => $q->whereIn('status', WsFloorOps::WASH_OPEN))
            ->when(! in_array($status, ['OPEN', 'ALL'], true), fn ($q) => $q->where('status', $status))
            ->when(! empty($f['buyer']), fn ($q) => $q->where('buyer_id', $f['buyer']))
            ->when(! empty($f['no']), fn ($q) => $q->where('requisition_no', 'ilike', $like($f['no'])))
            ->when(! empty($f['style']), fn ($q) => $q->where('style_no', 'ilike', $like($f['style'])))
            ->when(! empty($f['order']), fn ($q) => $q->where('order_no', 'ilike', $like($f['order'])))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('requisition_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('requisition_date', '<=', $f['to']))
            ->orderByDesc('requisition_date')->orderByDesc('requisition_no')->get()
            ->map(fn (WsWashRequisition $r) => $definition->present($r))->values()->all();
    }

    private function date(Request $request): string
    {
        $date = (string) $request->query('date', '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
            throw ValidationException::withMessages(['date' => __('workstudy.fo.v.date')]);
        }

        return Carbon::parse($date)->toDateString();
    }

    /** A floor's hour of a day (?floor=A-1F&date=2026-10-25&hour=2). @return array{0: object, 1: string, 2: int} */
    private function hourOf(Request $request): array
    {
        $code = (string) $request->query('floor', '');
        $floor = $code !== '' ? DB::table('floors')->where('company_id', $this->company())->where('code', $code)->first(['id', 'name', 'code']) : null;
        if (! $floor) {
            throw ValidationException::withMessages(['floor' => __('workstudy.fo.v.floor')]);
        }
        $hour = (int) $request->query('hour', 0);
        if (! in_array($hour, WsFloorOps::HOURS, true)) {
            throw ValidationException::withMessages(['hour' => __('workstudy.fo.v.hour')]);
        }

        return [$floor, $this->date($request), $hour];
    }

    /** A line by its floor's code and its own (?floor=A-1F&line=LINE01). */
    private function line(Request $request): PlanningLine
    {
        $floor = DB::table('floors')->where('company_id', $this->company())->where('code', (string) $request->query('floor', ''))->value('id');
        $line = $floor ? PlanningLine::query()->where('floor_id', $floor)->where('code', (string) $request->query('line', ''))->first() : null;
        if (! $line) {
            throw ValidationException::withMessages([$floor ? 'line' : 'floor' => $floor ? __('workstudy.fo.v.line') : __('workstudy.fo.v.floor')]);
        }

        return $line;
    }
}
