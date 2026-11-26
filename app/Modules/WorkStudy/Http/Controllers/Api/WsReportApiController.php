<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\WorkStudy\Support\WsCosting;
use App\Modules\WorkStudy\Support\WsReports;
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
 * Work Study → Reports (AnnotechERP 14/02) → IE Reports: Brickland's report
 * set with each report's parameters, a report previewed for the parameters
 * chosen, and the same report as a PDF to print or a workbook (Export to
 * Excel?). Everything travels by code — a report by its key, an office by
 * its unit code, a buyer by its code, floors and lines by theirs.
 */
class WsReportApiController extends ApiController
{
    /** The report set and what its parameters choose from. */
    public function options(): JsonResponse
    {
        $this->allow('workstudy.report.view');
        $company = $this->company();
        $floors = DB::table('floors')->where('company_id', $company)->orderBy('name')->orderBy('code')->get(['id', 'name', 'code', 'building_id']);

        return ApiResponse::ok([
            'reports' => collect(WsReports::REPORTS)->map(fn ($r, $k) => ['key' => $k, 'category' => $r[0], 'params' => $r[1], 'pending' => $r[2]])->values()->all(),
            'offices' => DB::table('factories')->where('company_id', $company)->orderBy('name')->get(['code', 'name'])->map(fn ($o) => ['value' => $o->code, 'label' => "{$o->name} ({$o->code})"])->values()->all(),
            'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->get()->map(fn (Buyer $b) => ['value' => $b->code, 'label' => WsCosting::buyerLabel($b)])->values()->all(),
            'orders' => Order::query()->whereNotIn('status', ['CANCELLED'])->orderByDesc('created_at')->limit(500)->get()
                ->map(fn (Order $o) => ['value' => $o->order_no, 'label' => trim($o->order_no.($o->style_no ? " — {$o->style_no}" : ''))])->values()->all(),
            'floors' => $floors->map(fn ($f) => ['value' => $f->code, 'label' => "{$f->name} — {$f->code}"])->values()->all(),
            'lines' => PlanningLine::query()->whereIn('floor_id', $floors->pluck('id'))->where('active', true)->orderBy('display_order')->orderBy('code')->get()
                ->map(fn (PlanningLine $l) => ['value' => ($floors->firstWhere('id', $l->floor_id)?->code ?? '').'/'.$l->code, 'floor' => $floors->firstWhere('id', $l->floor_id)?->code, 'label' => ($floors->firstWhere('id', $l->floor_id)?->code ?? '').' — '.$l->code])->values()->all(),
            'pluck' => WsReports::PLUCK,
            // New report run's pickers save the record itself, so they carry ids.
            'run' => [
                'units' => DB::table('factories')->where('company_id', $company)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($o) => ['value' => $o->id, 'label' => "{$o->name} ({$o->code})"])->values()->all(),
                'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->get()->map(fn (Buyer $b) => ['value' => $b->id, 'label' => WsCosting::buyerLabel($b)])->values()->all(),
                'floors' => $floors->map(fn ($f) => ['value' => $f->id, 'label' => "{$f->name} — {$f->code}"])->values()->all(),
            ],
        ]);
    }

    /** Preview: the report read now for the parameters chosen. */
    public function preview(Request $request, string $report): JsonResponse
    {
        $this->allow('workstudy.report.view');
        [$key, $f] = $this->filters($request, $report);

        return ApiResponse::ok(['report' => $key, 'category' => WsReports::REPORTS[$key][0], 'from' => $f['from'], 'to' => $f['to']] + WsReports::run($key, $f));
    }

    /** The report as a PDF (opened in its own tab to print) or a workbook. */
    public function export(Request $request, string $report, string $format): Response|StreamedResponse
    {
        $this->allow('workstudy.report.view');
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        [$key, $f] = $this->filters($request, $report);
        $result = WsReports::run($key, $f);
        abort_if($result['pending'] !== null, 404);
        $columns = array_map(fn ($c) => ['label' => __("workstudy.rp.col.{$c['key']}"), 'numeric' => in_array($c['type'], ['number', 'money', 'pct'], true)], $result['columns']);
        $cell = fn (array $r, array $c) => match ($c['type']) {
            'status' => isset($r[$c['key']]) ? __("workstudy.rp.status.{$r[$c['key']]}") : null,
            default => $r[$c['key']] ?? null,
        };
        $rows = array_map(fn ($r) => array_map(fn ($c) => $cell($r, $c), $result['columns']), $result['rows']);
        $total = $result['totals'] ? array_values(array_map(fn ($c) => $result['totals'][$c['key']] ?? null, $result['columns'])) : null;
        if ($total) {
            $total[0] = __('workstudy.cost.total');
        }
        $title = __("workstudy.rp.report.{$key}");
        $name = str_replace('_', '-', $key).'-'.$f['from'].($f['to'] !== $f['from'] ? "-{$f['to']}" : '');
        if ($format === 'pdf') {
            $meta = $this->meta($request, $key, $f);
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

    /**
     * A report's filters from the query string, read only for the parameters
     * it asks for: a day (Production Date) or a From → To range, the office's
     * floors narrowed to the floors and lines picked.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public static function filtersFor(string $report, array $q): array
    {
        $key = strtolower(str_replace('-', '_', $report));
        abort_unless(isset(WsReports::REPORTS[$key]), 404);
        $params = WsReports::REPORTS[$key][1];
        $company = app(TenantContext::class)->companyId();
        $day = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? Carbon::parse($v)->toDateString() : null;
        if (in_array('range', $params, true)) {
            $from = $day($q['from'] ?? null) ?? throw ValidationException::withMessages(['from' => __('workstudy.rp.v.from')]);
            $to = $day($q['to'] ?? null) ?? throw ValidationException::withMessages(['to' => __('workstudy.rp.v.to')]);
            if ($to < $from) {
                throw ValidationException::withMessages(['to' => __('workstudy.rp.v.range')]);
            }
            if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) >= WsReports::MAX_DAYS) {
                throw ValidationException::withMessages(['to' => __('workstudy.rp.v.too_long', ['days' => WsReports::MAX_DAYS])]);
            }
        } else {
            $from = $to = $day($q['date'] ?? null) ?? (in_array('date', $params, true) ? throw ValidationException::withMessages(['date' => __('workstudy.rp.v.date')]) : now()->toDateString());
        }
        $codes = fn ($v) => array_values(array_filter(array_map('trim', explode(',', (string) ($v ?? '')))));
        $floors = null;
        if (in_array('office', $params, true) && ! empty($q['office'])) {
            $floors = DB::table('floors')->join('buildings', 'buildings.id', '=', 'floors.building_id')->join('factories', 'factories.id', '=', 'buildings.factory_id')
                ->where('floors.company_id', $company)->where('factories.code', $q['office'])->pluck('floors.id')->all();
        }
        if (in_array('floors', $params, true) && $codes($q['floors'] ?? null)) {
            $picked = DB::table('floors')->where('company_id', $company)->whereIn('code', $codes($q['floors']))->pluck('id')->all();
            $floors = $floors === null ? $picked : array_values(array_intersect($floors, $picked));
        }
        $lines = null;
        if (in_array('lines', $params, true) && $codes($q['lines'] ?? null)) {
            $lines = [];
            foreach ($codes($q['lines']) as $code) {
                [$floor, $line] = array_pad(explode('/', $code, 2), 2, '');
                $id = PlanningLine::query()->where('code', $line)->whereIn('floor_id', DB::table('floors')->where('company_id', $company)->where('code', $floor)->pluck('id'))->value('id');
                if ($id) {
                    $lines[] = $id;
                }
            }
        }
        $buyer = in_array('buyer', $params, true) && ! empty($q['buyer']) ? Buyer::query()->where('code', $q['buyer'])->value('id') : null;

        return [$key, [
            'floors' => $floors, 'lines' => $lines, 'buyer_id' => $buyer,
            'order' => in_array('order', $params, true) && ! empty($q['order']) ? (string) $q['order'] : null,
            'style' => in_array('style', $params, true) && ! empty($q['style']) ? trim((string) $q['style']) : null,
            'from' => $from, 'to' => $to,
            'car' => in_array('car', $params, true) && filter_var($q['car'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]];
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function filters(Request $request, string $report): array
    {
        $request->validate(['office' => ['nullable', 'string', 'max:32'], 'buyer' => ['nullable', 'string', 'max:32'], 'order' => ['nullable', 'string', 'max:64'],
            'style' => ['nullable', 'string', 'max:64'], 'floors' => ['nullable', 'string', 'max:500'], 'lines' => ['nullable', 'string', 'max:2000'], 'pluck' => ['nullable', Rule::in(WsReports::PLUCK)]]);

        return self::filtersFor($report, $request->query());
    }

    /** What the PDF prints the report for. @return array<string, string> */
    private function meta(Request $request, string $key, array $f): array
    {
        $params = WsReports::REPORTS[$key][1];
        $meta = in_array('range', $params, true)
            ? [__('workstudy.rp.from') => $f['from'], __('workstudy.rp.to') => $f['to']]
            : [__('workstudy.rp.date') => $f['from']];
        foreach (['office' => 'office', 'buyer' => 'buyer', 'order' => 'order', 'floors' => 'floors', 'lines' => 'lines'] as $param => $label) {
            if (in_array($param, $params, true) && $request->filled($param)) {
                $meta[__("workstudy.rp.{$label}")] = (string) $request->query($param);
            }
        }

        return array_slice($meta, 0, 4, true);
    }

    private function company(): string
    {
        return app(TenantContext::class)->companyId();
    }
}
