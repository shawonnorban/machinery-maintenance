<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\WorkStudy\Models\WsLineCosting;
use App\Modules\WorkStudy\Models\WsLineProduction;
use App\Modules\WorkStudy\Models\WsOhCost;
use App\Modules\WorkStudy\Models\WsOhHead;
use App\Modules\WorkStudy\Models\WsOhSection;
use App\Modules\WorkStudy\Models\WsSpec;
use App\Modules\WorkStudy\Support\WsCosting;
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
 * Work Study → Costing (AnnotechERP 14/05), as Brickland keeps it:
 *
 * - Overhead Cost Allocation — each section's cost heads edited in place, and
 *   the Monthly Cost Re-arrange sheet: every head for one month, what it cost,
 *   its working days and the money re-arranged across the six production
 *   columns (Copy From builds the grid from another month; nothing is written
 *   until Save Sheet, and always into the month chosen);
 * - Daily Line Cost Sheet — a floor's day: the orders loaded on it and what
 *   each is costed at, what every line produced by overtime tier, and the
 *   profit or loss each line ran at.
 *
 * A floor travels by its code, a month as 2026-10, a day as 2026-10-25. The
 * sheets print (PDF) and export (Excel) as Brickland's do.
 */
class WsCostingApiController extends ApiController
{
    private const MONEY = ['nullable', 'numeric', 'min:0', 'max:999999999999'];

    /** What the costing forms and sheets choose from. */
    public function options(): JsonResponse
    {
        $this->allow('workstudy.costing.view');
        WsCosting::ensure($this->company());
        $years = WsOhCost::query()->pluck('period')->map(fn ($p) => (int) substr((string) $p, 0, 4))
            ->push((int) now()->format('Y'), (int) now()->format('Y') + 1)->unique()->sort()->values()->all();

        return ApiResponse::ok([
            'company' => ['value' => $this->company(), 'label' => DB::table('companies')->where('id', $this->company())->value('name')],
            'specs' => WsSpec::query()->orderBy('style_no')->orderBy('variation_no')->get()->map(fn (WsSpec $s) => ['value' => $s->id, 'label' => "{$s->style_no}/{$s->variation_no} — {$s->description}"])->values()->all(),
            'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->get()->map(fn (Buyer $b) => ['value' => $b->id, 'label' => WsCosting::buyerLabel($b)])->values()->all(),
            'floors' => DB::table('floors')->where('company_id', $this->company())->orderBy('name')->orderBy('code')->get(['id', 'name', 'code'])
                ->map(fn ($f) => ['value' => $f->id, 'code' => $f->code, 'label' => "{$f->name} — {$f->code}"])->values()->all(),
            'sections' => WsOhSection::query()->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (WsOhSection $s) => ['key' => $s->id, 'label' => $s->name, 'target' => $s->is_target, 'count' => WsOhHead::query()->where('section_id', $s->id)->count()])->values()->all(),
            'heads' => WsCosting::heads()->map(fn (WsOhHead $h) => ['value' => $h->id, 'label' => "{$h->section->name} — {$h->name}"])->values()->all(),
            'years' => $years,
            'periods' => WsOhCost::query()->distinct()->orderByDesc('period')->pluck('period')->all(),
            'columns' => WsCosting::COLUMNS,
        ]);
    }

    /* ---------------- Section Cost Heads ---------------- */

    /** A section's heads, as the grid lists them. */
    public function heads(string $section): JsonResponse
    {
        $this->allow('workstudy.costing.view');

        return ApiResponse::ok($this->headRows($this->section($section)));
    }

    /** Save a section's heads as listed; a head with figures stays (clear Active instead). */
    public function saveHeads(Request $request, string $section): JsonResponse
    {
        $this->allow('workstudy.costing.manage');
        $owner = $this->section($section);
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['nullable', 'string'],
            'rows.*.name' => ['required', 'string', 'max:120'],
            'rows.*.name_bn' => ['nullable', 'string', 'max:160'],
            'rows.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'rows.*.active' => ['boolean'],
        ], ['rows.*.name.required' => __('workstudy.cost.v.head_name')]);
        ksort($data['rows']);
        $rows = array_values($data['rows']);
        $existing = WsOhHead::query()->where('section_id', $owner->id)->get()->keyBy('id');
        $kept = collect($rows)->pluck('id')->filter()->all();
        foreach ($existing->except($kept) as $gone) {
            if (WsOhCost::query()->where('head_id', $gone->id)->exists()) {
                throw ValidationException::withMessages(['rows' => __('workstudy.cost.v.head_in_use', ['head' => $gone->name])]);
            }
        }
        DB::transaction(function () use ($rows, $existing, $kept, $owner): void {
            WsOhHead::query()->whereIn('id', $existing->except($kept)->keys())->delete();
            foreach ($rows as $i => $r) {
                $values = ['name' => trim($r['name']), 'name_bn' => $r['name_bn'] ?? null, 'sort_order' => $r['sort_order'] ?? $i + 1, 'active' => $r['active'] ?? true];
                $head = ! empty($r['id']) ? $existing->get($r['id']) : null;
                $head ? $head->update($values) : WsOhHead::query()->create($values + ['company_id' => $this->company(), 'section_id' => $owner->id]);
            }
        });

        return ApiResponse::ok($this->headRows($owner));
    }

    /* ---------------- Monthly Cost Re-arrange ---------------- */

    /** A month's sheet; `copy` builds the grid from another month's figures (nothing is written). */
    public function sheet(Request $request): JsonResponse
    {
        $this->allow('workstudy.costing.view');
        WsCosting::ensure($this->company());
        $period = $this->period($request->query('period'));
        $copy = $request->query('copy');
        $from = WsCosting::isPeriod($copy) ? $copy : $period;

        return ApiResponse::ok(['period' => $period, 'copied_from' => $from === $period ? null : $from, 'rows' => WsCosting::sheet($from)]);
    }

    /** Save Sheet: every head's month as typed, into the month chosen; a line left empty throughout is cleared. */
    public function saveSheet(Request $request): JsonResponse
    {
        $this->allow('workstudy.costing.manage');
        $data = $request->validate([
            'period' => ['required', 'string'],
            'rows' => ['present', 'array'],
            'rows.*.head_id' => ['required', 'string'],
            'rows.*.monthly_expense' => self::MONEY,
            'rows.*.work_days' => ['nullable', 'integer', 'min:1', 'max:31'],
            ...collect(WsCosting::COLUMNS)->mapWithKeys(fn ($c) => ["rows.*.{$c}" => self::MONEY])->all(),
        ], ['rows.*.work_days.max' => __('workstudy.cost.v.work_days'), 'rows.*.work_days.min' => __('workstudy.cost.v.work_days')]);
        $period = $this->period($data['period']);
        $heads = WsOhHead::query()->pluck('id')->flip();
        $errors = [];
        foreach ($data['rows'] as $i => $r) {
            $rearranged = array_sum(array_map(fn ($c) => (float) ($r[$c] ?? 0), WsCosting::COLUMNS));
            if (! $heads->has($r['head_id'])) {
                $errors["rows.{$i}.head_id"] = __('workstudy.cost.v.head');
            } elseif ($rearranged > 0 && ($r['monthly_expense'] ?? null) === null) {
                $errors["rows.{$i}.monthly_expense"] = __('workstudy.cost.v.expense_row', ['n' => $i + 1]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($data, $period): void {
            foreach ($data['rows'] as $r) {
                $values = ['monthly_expense' => $r['monthly_expense'] ?? null, 'work_days' => $r['work_days'] ?? null];
                foreach (WsCosting::COLUMNS as $c) {
                    $values[$c] = $r[$c] ?? null;
                }
                $row = WsOhCost::query()->where('period', $period)->where('head_id', $r['head_id'])->first();
                if ($values['monthly_expense'] === null) {
                    $row?->delete();

                    continue;
                }
                $row ? $row->update($values) : WsOhCost::query()->create($values + ['company_id' => $this->company(), 'period' => $period, 'head_id' => $r['head_id']]);
            }
        });

        return ApiResponse::ok(['period' => $period, 'copied_from' => null, 'rows' => WsCosting::sheet($period)]);
    }

    /* ---------------- Daily Line Cost Sheet ---------------- */

    /** Order Config Data for a floor and day. */
    public function config(Request $request): JsonResponse
    {
        $this->allow('workstudy.costing.view');
        [$floor, $date] = $this->day($request);

        return ApiResponse::ok(['rows' => WsCosting::configRows($floor->id, $date)]);
    }

    /** Save Order Config Data; an order costed at nothing throughout is removed. */
    public function saveConfig(Request $request): JsonResponse
    {
        $this->allow('workstudy.costing.manage');
        [$floor, $date] = $this->day($request);
        $money = ['nullable', 'numeric', 'min:0', 'max:99999999'];
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.order_no' => ['required', 'string', 'max:64', 'distinct'],
            'rows.*.style_no' => ['nullable', 'string', 'max:64'],
            'rows.*.buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $this->company())],
            'rows.*.gmt_item' => ['nullable', 'string', 'max:120'],
            'rows.*.bgt_as_cm_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rows.*.exc_rate' => $money, 'rows.*.smv' => ['nullable', 'numeric', 'min:0', 'max:999'], 'rows.*.fob' => $money,
            'rows.*.rate_cut' => $money, 'rows.*.rate_sew' => $money, 'rows.*.rate_fin' => $money,
        ]);
        $priced = ['bgt_as_cm_pct', 'exc_rate', 'smv', 'fob', 'rate_cut', 'rate_sew', 'rate_fin'];
        $errors = [];
        foreach ($data['rows'] as $i => $r) {
            $costed = collect($priced)->contains(fn ($k) => (float) ($r[$k] ?? 0) > 0);
            if ($costed && (float) ($r['smv'] ?? 0) <= 0) {
                $errors["rows.{$i}.smv"] = __('workstudy.cost.v.smv_row', ['order' => $r['order_no']]);
            } elseif ($costed && trim((string) ($r['style_no'] ?? '')) === '') {
                $errors["rows.{$i}.style_no"] = __('workstudy.cost.v.style_row', ['order' => $r['order_no']]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($data, $floor, $date, $priced): void {
            $saved = WsLineCosting::query()->where('floor_id', $floor->id)->whereDate('cost_date', $date)->get()->keyBy('order_no');
            foreach ($data['rows'] as $r) {
                $row = $saved->get($r['order_no']);
                if (! collect($priced)->contains(fn ($k) => (float) ($r[$k] ?? 0) > 0)) {
                    $row?->delete();

                    continue;
                }
                $values = collect($r)->only(['buyer_id', 'style_no', 'gmt_item', ...$priced])->all();
                $row ? $row->update($values) : WsLineCosting::query()->create($values + ['company_id' => $this->company(), 'floor_id' => $floor->id, 'cost_date' => $date, 'order_no' => $r['order_no']]);
            }
        });

        return ApiResponse::ok(['rows' => WsCosting::configRows($floor->id, $date)]);
    }

    /** Line Wise Prod for a floor and day. */
    public function lines(Request $request): JsonResponse
    {
        $this->allow('workstudy.costing.view');
        [$floor, $date] = $this->day($request);

        return ApiResponse::ok(['rows' => WsCosting::lineRows($floor->id, $date)]);
    }

    /** Save Line Wise Prod; a line that made nothing and has no order is cleared. */
    public function saveLines(Request $request): JsonResponse
    {
        $this->allow('workstudy.costing.manage');
        [$floor, $date] = $this->day($request);
        $count = ['nullable', 'integer', 'min:0', 'max:9999999'];
        $minutes = ['nullable', 'numeric', 'min:0', 'max:99999999'];
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.planning_line_id' => ['required', Rule::exists('planning_lines', 'id')->where('company_id', $this->company())->where('floor_id', $floor->id)],
            'rows.*.buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $this->company())],
            'rows.*.order_no' => ['nullable', 'string', 'max:64'],
            'rows.*.item_name' => ['nullable', 'string', 'max:120'],
            'rows.*.op' => ['nullable', 'integer', 'min:0', 'max:999'], 'rows.*.aso' => ['nullable', 'integer', 'min:0', 'max:999'],
            'rows.*.wh' => ['nullable', 'numeric', 'min:0', 'max:24'], 'rows.*.target' => $count,
            ...collect(['g', ...WsCosting::TIERS])->mapWithKeys(fn ($t) => ["rows.*.prod_{$t}" => $count])->all(),
            ...collect(WsCosting::TIERS)->mapWithKeys(fn ($t) => ["rows.*.manot_{$t}" => $minutes])->all(),
        ], ['rows.*.wh.max' => __('workstudy.cost.v.wh')]);
        $errors = [];
        foreach ($data['rows'] as $i => $r) {
            $made = collect(['g', ...WsCosting::TIERS])->sum(fn ($t) => (int) ($r["prod_{$t}"] ?? 0));
            if ($made > 0 && trim((string) ($r['order_no'] ?? '')) === '') {
                $errors["rows.{$i}.order_no"] = __('workstudy.cost.v.line_order', ['n' => $i + 1]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($data, $floor, $date): void {
            $saved = WsLineProduction::query()->where('floor_id', $floor->id)->whereDate('prod_date', $date)->get()->keyBy('planning_line_id');
            foreach ($data['rows'] as $r) {
                $row = $saved->get($r['planning_line_id']);
                $made = collect(['g', ...WsCosting::TIERS])->sum(fn ($t) => (int) ($r["prod_{$t}"] ?? 0));
                if ($made === 0 && trim((string) ($r['order_no'] ?? '')) === '') {
                    $row?->delete();

                    continue;
                }
                $values = collect($r)->except('planning_line_id')->map(fn ($v, $k) => str_starts_with($k, 'prod_') || str_starts_with($k, 'manot_') ? ($v ?? 0) : $v)->all();
                $row ? $row->update($values) : WsLineProduction::query()->create($values + ['company_id' => $this->company(), 'floor_id' => $floor->id, 'prod_date' => $date, 'planning_line_id' => $r['planning_line_id']]);
            }
        });

        return ApiResponse::ok(['rows' => WsCosting::lineRows($floor->id, $date)]);
    }

    /** Overall Profit Lost for a floor and day — every figure derived. */
    public function profit(Request $request): JsonResponse
    {
        $this->allow('workstudy.costing.view');
        [$floor, $date] = $this->day($request);

        return ApiResponse::ok(WsCosting::profit($floor->id, $date));
    }

    /* ---------------- PDF and Excel ---------------- */

    /** A sheet as a PDF (`format` pdf) or a workbook (xlsx): the month's sheet, or a floor's day. */
    public function export(Request $request, string $sheet, string $format): Response|StreamedResponse
    {
        $this->allow('workstudy.costing.view');
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        [$title, $columns, $rows, $meta, $total, $name] = match ($sheet) {
            'oh-sheet' => $this->sheetTable($request),
            'day-config' => $this->configTable($request),
            'day-lines' => $this->linesTable($request),
            'day-profit' => $this->profitTable($request),
            default => abort(404),
        };
        if ($format === 'pdf') {
            $subtitle = DB::table('companies')->where('id', $this->company())->value('name').' · '.trans_choice('workstudy.cost.rows', count($rows), ['count' => count($rows)]);
            $body = (new TablePdf($title, $columns, $rows, $meta, $total, $subtitle))->output();

            return response($body, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'.pdf"']);
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
    private function sheetTable(Request $request): array
    {
        $period = $this->period($request->query('period'));
        $rows = WsCosting::sheet($period);
        $num = fn (string $k) => ['label' => __("workstudy.cost.sheet.col.{$k}"), 'numeric' => true];
        $columns = [['label' => __('workstudy.cost.sheet.col.section')], ['label' => __('workstudy.cost.sheet.col.particular')], $num('monthly_expense'), $num('work_days'), ...array_map($num, WsCosting::COLUMNS)];
        $keys = ['monthly_expense', 'work_days', ...WsCosting::COLUMNS];
        $body = array_values(array_map(fn ($r) => [$r['section'], $r['particular'], ...array_map(fn ($k) => $r[$k] === null ? null : (float) $r[$k], $keys)], array_filter($rows, fn ($r) => $r['monthly_expense'] !== null)));
        $sum = fn (string $k) => array_sum(array_map(fn ($r) => (float) $r[$k], $rows));
        $total = [__('workstudy.cost.total'), null, $sum('monthly_expense'), null, ...array_map($sum, WsCosting::COLUMNS)];
        $label = Carbon::createFromFormat('Y-m-d', "{$period}-01")->format('F Y');

        return [__('workstudy.cost.sheet.title'), $columns, $body, [__('workstudy.cost.sheet.month') => $label], $total, "monthly-cost-{$period}"];
    }

    /** @return array{0: string, 1: list<array{label: string, numeric?: bool}>, 2: list<list<mixed>>, 3: array<string, string>, 4: ?list<mixed>, 5: string} */
    private function configTable(Request $request): array
    {
        [$floor, $date] = $this->day($request);
        $buyers = Buyer::query()->pluck('short_name', 'id');
        $keys = ['buyer', 'style_no', 'order_no', 'gmt_item', 'bgt_as_cm_pct', 'exc_rate', 'smv', 'fob', 'rate_cut', 'rate_sew', 'rate_fin'];
        $columns = array_map(fn ($k) => ['label' => __("workstudy.cost.day.col.{$k}"), 'numeric' => ! in_array($k, ['buyer', 'style_no', 'order_no', 'gmt_item'], true)], $keys);
        $rows = array_map(fn ($r) => array_map(fn ($k) => match ($k) {
            'buyer' => $buyers[$r['buyer_id']] ?? null,
            'style_no', 'order_no', 'gmt_item' => $r[$k],
            default => $r[$k] === null ? null : (float) $r[$k],
        }, $keys), WsCosting::configRows($floor->id, $date));

        return [__('workstudy.cost.day.config_title'), $columns, $rows, $this->dayMeta($floor, $date), null, "order-config-{$floor->code}-{$date}"];
    }

    /** @return array{0: string, 1: list<array{label: string, numeric?: bool}>, 2: list<list<mixed>>, 3: array<string, string>, 4: ?list<mixed>, 5: string} */
    private function linesTable(Request $request): array
    {
        [$floor, $date] = $this->day($request);
        $buyers = Buyer::query()->pluck('short_name', 'id');
        $keys = ['line', 'buyer', 'order_no', 'item_name', 'op', 'aso', 'wh', 'target', 'prod_total', 'prod_g', 'prod_aot', 'prod_eot', 'prod_2ot', 'prod_5ot', 'manot_aot', 'manot_eot', 'manot_2ot', 'manot_5ot', 'manot_total'];
        $columns = array_map(fn ($k) => ['label' => __("workstudy.cost.day.col.{$k}"), 'numeric' => ! in_array($k, ['line', 'buyer', 'order_no', 'item_name'], true)], $keys);
        $rows = array_map(function ($r) use ($keys, $buyers) {
            $r['buyer'] = $buyers[$r['buyer_id']] ?? null;
            $r['prod_total'] = collect(['g', ...WsCosting::TIERS])->sum(fn ($t) => (int) $r["prod_{$t}"]);
            $r['manot_total'] = collect(WsCosting::TIERS)->sum(fn ($t) => (float) $r["manot_{$t}"]);

            return array_map(fn ($k) => $r[$k] ?? null, $keys);
        }, array_values(array_filter(WsCosting::lineRows($floor->id, $date), fn ($r) => $r['saved'])));

        return [__('workstudy.cost.day.lines_title'), $columns, $rows, $this->dayMeta($floor, $date), null, "line-wise-prod-{$floor->code}-{$date}"];
    }

    /** @return array{0: string, 1: list<array{label: string, numeric?: bool}>, 2: list<list<mixed>>, 3: array<string, string>, 4: ?list<mixed>, 5: string} */
    private function profitTable(Request $request): array
    {
        [$floor, $date] = $this->day($request);
        $data = WsCosting::profit($floor->id, $date);
        $keys = ['line', 'buyer', 'order_no', 'item_name', 'effi', 'fob_earn', 'sal_g', 'oh_g', 'cm_g', 'pl_g'];
        foreach (WsCosting::TIERS as $t) {
            array_push($keys, "otbill_{$t}", "oh_{$t}", "cm_{$t}", "pl_{$t}");
        }
        array_push($keys, 'total_cost', 'cm_earn', 'profit');
        $columns = array_map(fn ($k) => ['label' => __('workstudy.cost.day.col.'.preg_replace('/_(aot|eot|2ot|5ot|g)$/', '', $k), ['tier' => preg_match('/_(aot|eot|2ot|5ot|g)$/', $k, $m) ? $m[1] : '']), 'numeric' => ! in_array($k, ['line', 'buyer', 'order_no', 'item_name'], true)], $keys);
        $rows = array_map(fn ($r) => array_map(fn ($k) => $r[$k] ?? null, $keys), $data['rows']);
        $total = array_map(fn ($k) => match ($k) {
            'line' => __('workstudy.cost.total'),
            'fob_earn', 'total_cost', 'cm_earn', 'profit' => $data['totals'][$k],
            default => null,
        }, $keys);

        return [__('workstudy.cost.day.profit_title'), $columns, $rows, $this->dayMeta($floor, $date), $data['rows'] ? $total : null, "profit-lost-{$floor->code}-{$date}"];
    }

    /* ---------------- helpers ---------------- */

    private function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /** @return array<string, string> */
    private function dayMeta(object $floor, string $date): array
    {
        return [__('workstudy.cost.day.floor') => "{$floor->name} — {$floor->code}", __('workstudy.cost.day.date') => $date];
    }

    private function section(string $id): WsOhSection
    {
        return WsOhSection::query()->findOrFail($id);
    }

    /** @return list<array<string, mixed>> */
    private function headRows(WsOhSection $section): array
    {
        $used = WsOhCost::query()->select('head_id')->distinct()->pluck('head_id')->flip();

        return WsOhHead::query()->where('section_id', $section->id)->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (WsOhHead $h) => ['id' => $h->id, 'name' => $h->name, 'name_bn' => $h->name_bn, 'sort_order' => $h->sort_order, 'active' => $h->active, 'in_use' => $used->has($h->id) ? 1 : 0])->values()->all();
    }

    private function period(mixed $period): string
    {
        if (! WsCosting::isPeriod(is_string($period) ? $period : null)) {
            throw ValidationException::withMessages(['period' => __('workstudy.cost.v.period')]);
        }

        return $period;
    }

    /**
     * The floor (by its code) and day a Daily Line Cost tab is about.
     *
     * @return array{0: object, 1: string}
     */
    private function day(Request $request): array
    {
        $code = (string) $request->query('floor', $request->input('floor', ''));
        $date = (string) $request->query('date', $request->input('date', ''));
        $floor = $code !== '' ? DB::table('floors')->where('company_id', $this->company())->where('code', $code)->first(['id', 'name', 'code']) : null;
        $errors = [];
        if (! $floor) {
            $errors['floor'] = __('workstudy.cost.v.floor');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
            $errors['date'] = __('workstudy.cost.v.date');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [$floor, $date];
    }
}
