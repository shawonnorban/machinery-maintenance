<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Http\Controllers\Api;

use App\Modules\GatePass\Models\GatePass;
use App\Modules\GatePass\Models\GatePassIn;
use App\Modules\GatePass\Models\GatePassInLine;
use App\Modules\GatePass\Models\GatePassItem;
use App\Modules\GatePass\Models\GatePassLine;
use App\Modules\GatePass\Models\GatePassLocation;
use App\Modules\GatePass\Models\GatePassLocationType;
use App\Modules\GatePass\Models\GatePassPurpose;
use App\Modules\GatePass\Support\GatePassBook;
use App\Modules\HrPayroll\Models\HrEmployee;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Pdf\TablePdf;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gate Pass → Dashboard and Reports (AnnotechERP 11/02), as Brickland lays
 * them out. The dashboard: today's OUT and IN, pending, partial, short and
 * overdue returns, OUT against IN over the last fourteen days, how the
 * returnable item passes have come back, passes by purpose and by location,
 * what is overdue and the latest passes. The ten reports share one filter
 * bar — dates, category, type, purpose, location and a search — each with
 * its rows and a Total row, as Excel and as a PDF to print.
 */
class GatePassReportApiController extends ApiController
{
    /** Each report's columns: `:n` sums into the Total row, `:f` is a number that does not. */
    private const REPORTS = [
        'register' => ['gp_no', 'date', 'category', 'type', 'purpose', 'destination', 'taken_by', 'items', 'out_qty:n', 'returned:n', 'short:n', 'status'],
        'out' => ['gp_no', 'out_time', 'category', 'type', 'purpose', 'destination', 'taken_by', 'vehicle_no', 'items', 'out_qty:n', 'out_by'],
        'in' => ['gpi_no', 'in_time', 'gp_no', 'destination', 'taken_by', 'items', 'in_qty:n', 'entry', 'received_by'],
        'pending' => ['gp_no', 'out_date', 'expected_return', 'destination', 'taken_by', 'items', 'out_qty:n', 'returned:n', 'balance:n', 'return'],
        'overdue' => ['gp_no', 'out_date', 'expected_return', 'days_overdue:f', 'destination', 'taken_by', 'items', 'out_qty:n', 'returned:n', 'balance:n'],
        'short' => ['gp_no', 'date', 'destination', 'item_code', 'item', 'unit', 'out_qty:n', 'returned:n', 'short:n', 'reason', 'status'],
        'location' => ['location', 'location_type', 'passes:n', 'out_qty:n', 'returned:n', 'short:n', 'balance:n'],
        'purpose' => ['purpose', 'passes:n', 'out_qty:n', 'returned:n', 'short:n', 'balance:n'],
        'employee' => ['card_no', 'employee', 'department', 'designation', 'passes:n', 'out_qty:n', 'returned:n', 'short:n', 'balance:n'],
        'item' => ['item_code', 'item', 'unit', 'passes:n', 'out_qty:n', 'returned:n', 'short:n', 'balance:n'],
    ];

    public function dashboard(): JsonResponse
    {
        $this->allowAny(GatePassApiController::READERS);
        $today = today();
        $all = GatePass::query()->get();
        $figures = GatePassBook::figures($all);
        $counted = $all->whereNotIn('status', ['DRAFT', 'CANCELLED', 'REJECTED']);
        $itemBack = $all->where('kind', 'ITEM')->where('pass_type', 'RETURNABLE')->whereIn('status', ['OUT', 'PARTIAL', 'SHORT', 'CLOSED']);
        $overdue = $all->where('pass_type', 'RETURNABLE')->whereIn('status', GatePass::AWAITING_RETURN)
            ->filter(fn (GatePass $p) => $p->expected_return && $p->expected_return->lt($today))->sortBy('expected_return');
        $from = $today->copy()->subDays(13);
        $outs = $all->filter(fn (GatePass $p) => $p->out_at && $p->out_at->gte($from))->countBy(fn (GatePass $p) => $p->out_at->toDateString());
        $ins = GatePassIn::query()->where('in_at', '>=', $from)->get()->countBy(fn (GatePassIn $i) => $i->in_at->toDateString());
        $purposes = GatePassPurpose::query()->pluck('name', 'id');
        $locations = GatePassLocation::query()->pluck('name', 'id');
        $returns = $itemBack->countBy(fn (GatePass $p) => $figures[$p->id]['return']);
        $latest = $all->sortByDesc(fn (GatePass $p) => [$p->gp_date->toDateString(), $p->gp_no])->take(10)->values();
        $overdueRows = GatePassBook::rows($overdue->values(), null);

        return ApiResponse::ok([
            'tiles' => [
                'total' => $all->count(), 'today_out' => $all->filter(fn (GatePass $p) => $p->out_at?->isSameDay($today))->count(),
                'today_in' => GatePassIn::query()->whereDate('in_at', $today)->count(),
                'pending_return' => $all->where('pass_type', 'RETURNABLE')->where('status', 'OUT')->count(), 'partial_return' => $all->where('status', 'PARTIAL')->count(),
                'short' => $all->filter(fn (GatePass $p) => $figures[$p->id]['short'] > 0.0005)->count(), 'overdue' => $overdue->count(),
                'closed' => $all->whereIn('status', ['CLOSED', 'COMPLETED'])->count(),
            ],
            'movement' => collect(range(0, 13))->map(fn (int $d) => $from->copy()->addDays($d)->toDateString())
                ->map(fn (string $date) => ['date' => $date, 'out' => $outs[$date] ?? 0, 'in' => $ins[$date] ?? 0])->values()->all(),
            'returns' => ['total' => $itemBack->count(), 'FULL' => $returns['FULL'] ?? 0, 'PARTIAL' => $returns['PARTIAL'] ?? 0, 'SHORT' => $returns['SHORT'] ?? 0, 'PENDING' => $returns['PENDING'] ?? 0],
            'purposes' => $counted->countBy('purpose_id')->sortDesc()->take(8)->map(fn ($n, $id) => ['label' => $purposes[$id] ?? '—', 'count' => $n])->values()->all(),
            'locations' => $counted->where('kind', 'ITEM')->whereNotNull('location_id')->countBy('location_id')->sortDesc()->take(8)
                ->map(fn ($n, $id) => ['label' => $locations[$id] ?? '—', 'count' => $n])->values()->all(),
            'overdue' => $overdueRows,
            'latest' => GatePassBook::rows($latest, null),
        ]);
    }

    /** What the report filters pick from. */
    public function options(): JsonResponse
    {
        $this->allowAny(GatePassApiController::READERS);

        return ApiResponse::ok([
            'reports' => array_keys(self::REPORTS),
            'purposes' => GatePassPurpose::query()->orderBy('code')->get()->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])->all(),
            'locations' => GatePassLocation::query()->orderBy('name')->get()->map(fn ($l) => ['value' => $l->id, 'label' => $l->name])->all(),
        ]);
    }

    public function show(Request $request, string $report): JsonResponse
    {
        $this->allowAny(GatePassApiController::READERS);
        abort_unless(isset(self::REPORTS[$report]), 404);
        $rows = $this->rows($report, $request);

        return ApiResponse::ok(['columns' => $this->columns($report), 'rows' => $rows, 'total' => $this->total($report, $rows), 'subtitle' => $this->subtitle(count($rows))]);
    }

    public function excel(Request $request, string $report): StreamedResponse
    {
        $this->allowAny(GatePassApiController::READERS);
        abort_unless(isset(self::REPORTS[$report]), 404);
        $rows = $this->rows($report, $request);
        $columns = $this->columns($report);
        $total = $this->total($report, $rows);

        return response()->streamDownload(function () use ($rows, $columns, $total): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_column($columns, 'label')));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($c) => $r[$c['key']] ?? '', $columns)));
            }
            if ($total) {
                $writer->addRow(Row::fromValues(array_map(fn ($c) => $c['key'] === $columns[0]['key'] ? __('gatepass.rpt.total') : ($total[$c['key']] ?? ''), $columns)));
            }
            $writer->close();
        }, "gate-pass-{$report}-".now()->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Print / PDF: the same rows on landscape A4, opened in the browser to print or save. */
    public function pdf(Request $request, string $report): Response
    {
        $this->allowAny(GatePassApiController::READERS);
        abort_unless(isset(self::REPORTS[$report]), 404);
        $rows = $this->rows($report, $request);
        $columns = $this->columns($report);
        $total = $this->total($report, $rows);
        $body = (new TablePdf(
            __("gatepass.rpt.titles.{$report}"),
            array_map(fn ($c) => ['label' => $c['label'], 'numeric' => $c['numeric']], $columns),
            array_map(fn ($r) => array_map(fn ($c) => $r[$c['key']] ?? null, $columns), $rows),
            [],
            $total ? array_map(fn ($c) => $c['key'] === $columns[0]['key'] ? __('gatepass.rpt.total') : ($total[$c['key']] ?? null), $columns) : null,
            $this->subtitle(count($rows)),
        ))->output();

        return response($body, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="gate-pass-'.$report.'.pdf"']);
    }

    /** @return list<array{key: string, label: string, numeric: bool, total: bool}> */
    private function columns(string $report): array
    {
        return array_map(function (string $c) use ($report): array {
            [$key, $flag] = array_pad(explode(':', $c), 2, '');
            $own = "gatepass.rpt.col_{$report}.{$key}";

            return ['key' => $key, 'label' => __($own) !== $own ? __($own) : __("gatepass.rpt.col.{$key}"), 'numeric' => $flag !== '', 'total' => $flag === 'n'];
        }, self::REPORTS[$report]);
    }

    /** @param list<array<string, mixed>> $rows */
    private function total(string $report, array $rows): ?array
    {
        $keys = array_column(array_filter($this->columns($report), fn ($c) => $c['total']), 'key');

        return $keys ? array_combine($keys, array_map(fn ($k) => round(array_sum(array_column($rows, $k)), 3), $keys)) : null;
    }

    private function subtitle(int $rows): string
    {
        return Company::query()->whereKey(app(TenantContext::class)->companyId())->value('name').' · '.trans_choice('gatepass.rpt.rows', $rows, ['count' => $rows]);
    }

    /**
     * The passes the filters keep: a category, type, purpose or location, a
     * search across number, carrier, employee, item and destination, and the
     * dates on the field the report is about.
     *
     * @return Builder<GatePass>
     */
    private function passes(Request $request, ?string $dateColumn = 'gp_date'): Builder
    {
        $f = fn (string $k): string => trim((string) $request->query($k, ''));
        $q = GatePass::query()->where('status', '!=', 'DRAFT');
        foreach (['category' => 'kind', 'type' => 'pass_type', 'purpose' => 'purpose_id', 'location' => 'location_id'] as $key => $column) {
            if ($v = $f($key)) {
                $q->where($column, $v);
            }
        }
        if ($dateColumn && ($v = $f('from'))) {
            $q->whereDate($dateColumn, '>=', $v);
        }
        if ($dateColumn && ($v = $f('to'))) {
            $q->whereDate($dateColumn, '<=', $v);
        }
        if ($v = $f('q')) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $v).'%';
            $q->where(fn ($w) => $w->where('gp_no', 'ilike', $like)->orWhere('carrier_name', 'ilike', $like)->orWhere('destination_text', 'ilike', $like)
                ->orWhere('style', 'ilike', $like)->orWhere('po_no', 'ilike', $like)
                ->orWhereIn('employee_id', HrEmployee::query()->where(fn ($e) => $e->where('card_no', 'ilike', $like)->orWhere('name', 'ilike', $like))->select('id'))
                ->orWhereIn('id', GatePassLine::query()->whereIn('item_id', GatePassItem::query()->where(fn ($i) => $i->where('name', 'ilike', $like)->orWhere('code', 'ilike', $like))->select('id'))->select('gate_pass_id'))
                ->orWhereIn('location_id', GatePassLocation::query()->where('name', 'ilike', $like)->select('id')));
        }

        return $q;
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $report, Request $request): array
    {
        $loc = app()->getLocale();
        $kind = fn (string $k) => __("gatepass.gp.kind.{$k}");
        $type = fn (string $t) => __("gatepass.gp.type.{$t}");
        $status = fn (string $s) => __("gatepass.gp.status.{$s}");
        $when = fn ($t) => $t ? CarbonImmutable::parse($t)->format('Y-m-d H:i') : null;
        $base = fn (GatePass $p, array $r) => [
            'gp_no' => $p->gp_no, 'date' => $r['date'], 'category' => $kind($p->kind), 'type' => $type($p->pass_type), 'purpose' => $r['purpose'],
            'destination' => $r['destination'], 'taken_by' => $r['taken_by'], 'status' => $status($p->status),
        ];
        $itemsOf = fn (array $r) => $r['unit'] && count($r['items']) === 1 ? $r['items'][0].' ('.GatePassBook::withUnit((float) $r['out'], $r['unit'], $loc).')' : implode(', ', $r['items']);

        switch ($report) {
            case 'register':
            case 'out':
            case 'pending':
            case 'overdue':
                $q = $this->passes($request, $report === 'out' ? 'out_at' : 'gp_date');
                match ($report) {
                    'out' => $q->whereNotNull('out_at')->orderBy('out_at'),
                    'pending' => $q->where('pass_type', 'RETURNABLE')->whereIn('status', GatePass::AWAITING_RETURN)->orderByRaw('expected_return asc nulls last'),
                    'overdue' => $q->where('pass_type', 'RETURNABLE')->whereIn('status', GatePass::AWAITING_RETURN)->whereDate('expected_return', '<', today())->orderBy('expected_return'),
                    default => $q->orderBy('gp_date')->orderBy('gp_no'),
                };
                $passes = $q->get()->keyBy('gp_no');
                $users = DB::table('users')->whereIn('id', $passes->pluck('out_by')->filter()->unique())->pluck('name', 'id');

                return array_map(function (array $r) use ($passes, $report, $base, $itemsOf, $when, $users): array {
                    $p = $passes[$r['gp_no']];
                    $row = $base($p, $r) + ['items' => $itemsOf($r), 'out_qty' => $r['out'], 'returned' => $r['returned'], 'short' => $r['short'], 'balance' => $r['balance']];

                    return $row + match ($report) {
                        'out' => ['out_time' => $when($r['out_at']), 'vehicle_no' => $p->vehicle_no, 'out_by' => $users[$p->out_by] ?? null],
                        'pending', 'overdue' => [
                            'out_date' => $p->out_at?->toDateString(), 'expected_return' => $r['expected_return'], 'return' => $r['return'] ? __("gatepass.gp.return.{$r['return']}") : null,
                            'days_overdue' => $p->expected_return ? max(0, (int) $p->expected_return->diffInDays(today())) : null,
                        ],
                        default => [],
                    };
                }, GatePassBook::rows($passes->values(), null));

            case 'in':
                $passIds = $this->passes($request, null)->select('id');
                $ins = GatePassIn::query()->whereIn('gate_pass_id', $passIds)
                    ->when(trim((string) $request->query('from', '')), fn ($q, $v) => $q->whereDate('in_at', '>=', $v))
                    ->when(trim((string) $request->query('to', '')), fn ($q, $v) => $q->whereDate('in_at', '<=', $v))->orderBy('in_at')->get();
                $passes = GatePass::query()->whereIn('id', $ins->pluck('gate_pass_id')->unique())->get()->keyBy('id');
                $rows = collect(GatePassBook::rows($passes->values(), null))->keyBy('gp_no');
                $lines = GatePassLine::query()->whereIn('gate_pass_id', $passes->keys())->get()->keyBy('id');
                $items = GatePassItem::query()->whereIn('id', $lines->pluck('item_id')->unique())->pluck('name', 'id');
                $inLines = GatePassInLine::query()->whereIn('gate_pass_in_id', $ins->pluck('id'))->get()->groupBy('gate_pass_in_id');
                $users = DB::table('users')->whereIn('id', $ins->pluck('received_by')->filter()->unique())->pluck('name', 'id');

                return $ins->map(function (GatePassIn $in) use ($passes, $rows, $lines, $items, $inLines, $users, $loc): array {
                    $p = $passes[$in->gate_pass_id];
                    $set = $inLines[$in->id] ?? collect();

                    return [
                        'gpi_no' => $in->gpi_no, 'in_time' => $in->in_at->format('Y-m-d H:i'), 'gp_no' => $p->gp_no, 'destination' => $rows[$p->gp_no]['destination'],
                        'taken_by' => $rows[$p->gp_no]['taken_by'],
                        'items' => $set->map(fn ($x) => ($items[$lines[$x->gate_pass_line_id]->item_id ?? ''] ?? '').': '.GatePassBook::withUnit((float) $x->quantity, $lines[$x->gate_pass_line_id]->unit ?? null, $loc))->implode(', '),
                        'in_qty' => round((float) $set->sum('quantity'), 3),
                        'entry' => $in->final ? ($in->short_reason ? __('gatepass.gp.in.final_short', ['reason' => __("gatepass.gp.short_reason.{$in->short_reason}")]) : __('gatepass.gp.in.final')) : __('gatepass.gp.in.partial'),
                        'received_by' => $users[$in->received_by] ?? null,
                    ];
                })->values()->all();

            case 'short':
                $passes = $this->passes($request)->get()->keyBy('id');
                $lines = GatePassLine::query()->whereIn('gate_pass_id', $passes->keys())->where('short_qty', '>', 0)->orderBy('sort_order')->get();
                $returned = GatePassBook::returned($passes->keys());
                $items = GatePassItem::query()->whereIn('id', $lines->pluck('item_id')->unique())->get()->keyBy('id');
                $rows = collect(GatePassBook::rows($passes->values(), null))->keyBy('gp_no');

                return $lines->sortBy(fn (GatePassLine $l) => [$passes[$l->gate_pass_id]->gp_no, $l->sort_order])->map(fn (GatePassLine $l) => [
                    'gp_no' => $passes[$l->gate_pass_id]->gp_no, 'date' => $rows[$passes[$l->gate_pass_id]->gp_no]['date'], 'destination' => $rows[$passes[$l->gate_pass_id]->gp_no]['destination'],
                    'item_code' => $items[$l->item_id]->code ?? null, 'item' => $items[$l->item_id]->name ?? null, 'unit' => __("gatepass.master.opt.unit.{$l->unit}"),
                    'out_qty' => (float) $l->quantity, 'returned' => $returned[$l->id] ?? 0.0, 'short' => (float) $l->short_qty,
                    'reason' => $l->short_reason ? __("gatepass.gp.short_reason.{$l->short_reason}") : null, 'status' => $status($passes[$l->gate_pass_id]->status),
                ])->values()->all();

            default:
                return $this->grouped($report, $request);
        }
    }

    /**
     * Location-, Purpose-, Employee- and Item-wise: the passes that went out,
     * gathered by the one thing, with what went out, came back, was short
     * and is still out.
     *
     * @return list<array<string, mixed>>
     */
    private function grouped(string $report, Request $request): array
    {
        $passes = $this->passes($request)->whereIn('status', GatePass::WENT_OUT)
            ->when($report === 'location', fn ($q) => $q->whereNotNull('location_id'))->when($report === 'employee', fn ($q) => $q->whereNotNull('employee_id'))->get();
        $sum = fn (Collection $set, string $k) => round((float) $set->sum($k), 3);
        $totals = fn (Collection $set) => ['out_qty' => $sum($set, 'out'), 'returned' => $sum($set, 'returned'), 'short' => $sum($set, 'short'), 'balance' => $sum($set, 'balance')];

        if ($report === 'item') {
            $lines = GatePassLine::query()->whereIn('gate_pass_id', $passes->pluck('id'))->get();
            $returned = GatePassBook::returned($passes->pluck('id'));
            $items = GatePassItem::query()->whereIn('id', $lines->pluck('item_id')->unique())->get()->keyBy('id');
            $figs = $lines->map(fn (GatePassLine $l) => [
                'item_id' => $l->item_id, 'unit' => $l->unit, 'pass' => $l->gate_pass_id, 'out' => (float) $l->quantity, 'returned' => $returned[$l->id] ?? 0.0,
                'short' => (float) $l->short_qty, 'balance' => $passes->firstWhere('id', $l->gate_pass_id)?->returnable() ? max(0, (float) $l->quantity - ($returned[$l->id] ?? 0) - (float) $l->short_qty) : 0.0,
            ]);

            return $figs->groupBy(fn ($f) => $f['item_id'].'|'.$f['unit'])->map(fn (Collection $set) => [
                'item_code' => $items[$set->first()['item_id']]->code ?? null, 'item' => $items[$set->first()['item_id']]->name ?? null,
                'unit' => __("gatepass.master.opt.unit.{$set->first()['unit']}"), 'passes' => $set->pluck('pass')->unique()->count(),
            ] + $totals($set))->sortBy('item_code')->values()->all();
        }

        $figures = GatePassBook::figures($passes);
        $set = $passes->map(fn (GatePass $p) => $figures[$p->id] + ['pass' => $p]);

        return match ($report) {
            'location' => (function () use ($set, $totals): array {
                $locations = GatePassLocation::query()->get()->keyBy('id');
                $types = GatePassLocationType::query()->pluck('name', 'id');

                return $set->groupBy(fn ($f) => $f['pass']->location_id)->map(fn (Collection $g, $id) => [
                    'location' => $locations[$id]->name ?? '—', 'location_type' => $types[$locations[$id]->location_type_id ?? ''] ?? null, 'passes' => $g->count(),
                ] + $totals($g))->sortByDesc('passes')->values()->all();
            })(),
            'purpose' => (function () use ($set, $totals): array {
                $purposes = GatePassPurpose::query()->pluck('name', 'id');

                return $set->groupBy(fn ($f) => $f['pass']->purpose_id)->map(fn (Collection $g, $id) => ['purpose' => $purposes[$id] ?? '—', 'passes' => $g->count()] + $totals($g))
                    ->sortByDesc('passes')->values()->all();
            })(),
            default => (function () use ($set, $totals): array {
                $employees = HrEmployee::query()->whereIn('id', $set->map(fn ($f) => $f['pass']->employee_id)->unique())->get()->keyBy('id');
                $depts = DB::table('departments')->whereIn('id', $employees->pluck('department_id')->filter()->unique())->pluck('name', 'id');
                $desigs = DB::table('designations')->whereIn('id', $employees->pluck('designation_id')->filter()->unique())->pluck('name', 'id');

                return $set->groupBy(fn ($f) => $f['pass']->employee_id)->map(fn (Collection $g, $id) => [
                    'card_no' => $employees[$id]->card_no ?? null, 'employee' => $employees[$id]->name ?? null,
                    'department' => $depts[$employees[$id]->department_id ?? ''] ?? null, 'designation' => $desigs[$employees[$id]->designation_id ?? ''] ?? null, 'passes' => $g->count(),
                ] + $totals($g))->sortByDesc('passes')->values()->all();
            })(),
        };
    }

    /** @param list<string> $permissions */
    private function allowAny(array $permissions): void
    {
        if (! collect($permissions)->contains(fn ($p) => $this->caller()->can($p))) {
            $this->allow($permissions[0]);
        }
    }
}
