<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Http\Controllers\Api;

use App\Modules\WorkStudy\Models\WsSectionResult;
use App\Modules\WorkStudy\Models\WsSectionTarget;
use App\Modules\WorkStudy\Support\WsSectionProfit;
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
 * Work Study → Section Profitability (AnnotechERP 14/08), as Brickland keeps it:
 *
 * - Section Profit & Loss Setup → Income Target Config: every section for a
 *   month — daily target (in-house and sub-contracted beside it), Avg Rate,
 *   WrkDay/Mn — with Target Income worked out on save. A section left
 *   without a daily target writes nothing; Update brings another month's
 *   figures onto this one without touching a section already set.
 * - Section Profit & Loss Entry → SecWise Profit/Loss-Trans: every section
 *   for a day, IncomeTarget and Prod Target opening at the section's share of
 *   the month, Profit/Loss = income less overhead, material and expense.
 *   A section left at nothing writes nothing.
 *
 * A month travels as 2026-10, a day as 2026-10-25. Both sheets print (PDF)
 * and export (Excel), as Brickland's do.
 */
class WsSectionProfitApiController extends ApiController
{
    /** What the forms and sheets choose from. */
    public function options(): JsonResponse
    {
        $this->allow('workstudy.profit.view');
        $years = range((int) now()->format('Y') - 1, (int) now()->format('Y') + 1);

        return ApiResponse::ok([
            'company' => ['value' => $this->company(), 'label' => DB::table('companies')->where('id', $this->company())->value('name')],
            'years' => $years,
            'periods' => collect($years)->flatMap(fn ($y) => array_map(fn ($m) => sprintf('%04d-%02d', $y, $m), range(1, 12)))->values()->all(),
            // A section as Brickland's picker names it: Cutting — Cutting (section — department).
            'sections' => WsSectionProfit::sections()->map(fn ($s) => ['value' => $s->id, 'label' => trim($s->name.($s->department ? " — {$s->department}" : ''))])->values()->all(),
            'default_days' => WsSectionProfit::DEFAULT_DAYS,
        ]);
    }

    /* ---------------- Income Target Config ---------------- */

    public function targets(Request $request): JsonResponse
    {
        $this->allow('workstudy.profit.view');

        return ApiResponse::ok(['rows' => WsSectionProfit::targetSheet($this->period($request->query('period')))]);
    }

    /** Save: every section with a daily target, its Target Income worked out; a section cleared of its target loses it. */
    public function saveTargets(Request $request): JsonResponse
    {
        $this->allow('workstudy.profit.manage');
        $period = $this->period($request->input('period'));
        $number = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.section_id' => ['required', Rule::exists('sections', 'id')->where('company_id', $this->company())],
            'rows.*.sub_section' => ['nullable', 'string', 'max:120'],
            'rows.*.daily_target' => $number, 'rows.*.daily_target_inhouse' => $number, 'rows.*.daily_target_subcon' => $number, 'rows.*.avg_rate' => $number,
            'rows.*.work_days' => ['nullable', 'integer', 'min:1', 'max:31'],
        ]);
        ksort($data['rows']);
        $names = DB::table('sections')->whereIn('id', array_column($data['rows'], 'section_id'))->pluck('name', 'id');
        $errors = [];
        foreach ($data['rows'] as $i => $r) {
            if ((float) ($r['daily_target'] ?? 0) > 0 && (float) ($r['avg_rate'] ?? 0) <= 0) {
                $errors["rows.{$i}.avg_rate"] = __('workstudy.sp.v.rate_row', ['section' => $names[$r['section_id']] ?? '']);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($data, $period, $names): void {
            $saved = WsSectionTarget::query()->where('period', $period)->get()->keyBy('section_id');
            foreach ($data['rows'] as $r) {
                $row = $saved->get($r['section_id']);
                if ((float) ($r['daily_target'] ?? 0) <= 0) {
                    $row?->delete();

                    continue;
                }
                $days = (int) ($r['work_days'] ?? WsSectionProfit::DEFAULT_DAYS);
                $values = [
                    'sub_section' => blank($r['sub_section'] ?? null) ? WsSectionProfit::defaultSubSection((string) ($names[$r['section_id']] ?? '')) : $r['sub_section'],
                    'daily_target' => (float) $r['daily_target'], 'daily_target_inhouse' => $r['daily_target_inhouse'] ?? null, 'daily_target_subcon' => $r['daily_target_subcon'] ?? null,
                    'avg_rate' => (float) $r['avg_rate'], 'work_days' => $days,
                    'target_income' => WsSectionProfit::income((float) $r['daily_target'], (float) $r['avg_rate'], $days),
                ];
                $row ? $row->update($values) : WsSectionTarget::query()->create($values + ['company_id' => $this->company(), 'period' => $period, 'section_id' => $r['section_id']]);
            }
        });

        return ApiResponse::ok(['rows' => WsSectionProfit::targetSheet($period)]);
    }

    /** Update: another month's figures onto this one; a section already set here is left alone. */
    public function copyTargets(Request $request): JsonResponse
    {
        $this->allow('workstudy.profit.manage');
        $period = $this->period($request->input('period'));
        $from = (string) $request->input('from', '');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $from) || $from === $period) {
            throw ValidationException::withMessages(['from' => __('workstudy.sp.v.copy_from')]);
        }
        $busy = WsSectionTarget::query()->where('period', $period)->pluck('section_id')->flip();
        $copied = 0;
        DB::transaction(function () use ($from, $period, $busy, &$copied): void {
            foreach (WsSectionTarget::query()->where('period', $from)->get() as $t) {
                if ($busy->has($t->section_id)) {
                    continue;
                }
                $copy = $t->replicate();
                $copy->period = $period;
                $copy->save();
                $copied++;
            }
        });

        return ApiResponse::ok(['copied' => $copied, 'rows' => WsSectionProfit::targetSheet($period)]);
    }

    /* ---------------- SecWise Profit/Loss-Trans ---------------- */

    public function results(Request $request): JsonResponse
    {
        $this->allow('workstudy.profit.view');

        return ApiResponse::ok(['rows' => WsSectionProfit::resultSheet($this->date($request->query('date')))]);
    }

    /** Save: every section's day as typed; one left at nothing writes nothing, a posted one cleared is removed. */
    public function saveResults(Request $request): JsonResponse
    {
        $this->allow('workstudy.profit.manage');
        $date = $this->date($request->input('date'));
        $number = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.section_id' => ['required', Rule::exists('sections', 'id')->where('company_id', $this->company())],
            'rows.*.income_target' => $number, 'rows.*.income' => $number, 'rows.*.prod_target' => $number, 'rows.*.prod_qty' => $number,
            'rows.*.oh_cost' => $number, 'rows.*.mat_cost' => $number, 'rows.*.expense' => $number,
            'rows.*.comments' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($data, $date): void {
            $saved = WsSectionResult::query()->whereDate('result_date', $date)->get()->keyBy('section_id');
            foreach ($data['rows'] as $r) {
                $row = $saved->get($r['section_id']);
                $amounts = array_map(fn ($k) => (float) ($r[$k] ?? 0), ['income' => 'income', 'prod_qty' => 'prod_qty', 'oh_cost' => 'oh_cost', 'mat_cost' => 'mat_cost', 'expense' => 'expense']);
                // The targets alone are only what the row opened at: nothing earned, made or spent is nothing posted.
                if (array_sum($amounts) == 0 && blank($r['comments'] ?? null)) {
                    $row?->delete();

                    continue;
                }
                $values = $amounts + ['income_target' => $r['income_target'] ?? null, 'prod_target' => $r['prod_target'] ?? null, 'comments' => $r['comments'] ?? null];
                $row ? $row->update($values) : WsSectionResult::query()->create($values + [
                    'company_id' => $this->company(), 'result_date' => $date, 'section_id' => $r['section_id'], 'created_by' => $this->caller()->auditUserId(),
                ]);
            }
        });

        return ApiResponse::ok(['rows' => WsSectionProfit::resultSheet($date)]);
    }

    /* ---------------- PDF and Excel ---------------- */

    /** A month's income targets or a day's results as a PDF or a workbook. */
    public function export(Request $request, string $sheet, string $format): Response|StreamedResponse
    {
        $this->allow('workstudy.profit.view');
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        [$title, $columns, $rows, $meta, $total, $name] = match ($sheet) {
            'targets' => $this->targetsTable($request),
            'results' => $this->resultsTable($request),
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

    /** The sections set for the month (a section with no target prints nothing). */
    private function targetsTable(Request $request): array
    {
        $period = $this->period($request->query('period'));
        $keys = ['section', 'sub_section', 'unit', 'daily_target', 'daily_target_inhouse', 'daily_target_subcon', 'avg_rate', 'work_days', 'target_income', 'oh_calculated'];
        $text = ['section', 'sub_section', 'unit'];
        $columns = array_map(fn ($k) => ['label' => __("workstudy.sp.cfg.col.{$k}"), 'numeric' => ! in_array($k, $text, true)], $keys);
        $rows = array_map(fn ($r) => array_map(fn ($k) => $r[$k], $keys), array_values(array_filter(WsSectionProfit::targetSheet($period), fn ($r) => $r['id'])));
        $total = $rows ? array_map(fn ($k) => match ($k) {
            'section' => __('workstudy.cost.total'),
            'target_income', 'oh_calculated' => array_sum(array_map(fn ($r) => (float) $r[array_search($k, $keys, true)], $rows)),
            default => null,
        }, $keys) : null;

        return [__('workstudy.sp.cfg.title'), $columns, $rows, [__('workstudy.sp.month') => Carbon::createFromFormat('Y-m-d', "{$period}-01")->format('Y | F')], $total, "section-income-targets-{$period}"];
    }

    /** The sections posted for the day. */
    private function resultsTable(Request $request): array
    {
        $date = $this->date($request->query('date'));
        $keys = ['section', 'income_target', 'income', 'prod_target', 'prod_qty', 'oh_cost', 'mat_cost', 'expense', 'profit', 'comments'];
        $columns = array_map(fn ($k) => ['label' => __("workstudy.sp.trans.col.{$k}"), 'numeric' => ! in_array($k, ['section', 'comments'], true)], $keys);
        $rows = array_map(fn ($r) => array_map(fn ($k) => $r[$k], $keys), array_values(array_filter(WsSectionProfit::resultSheet($date), fn ($r) => $r['id'])));
        $total = $rows ? array_map(fn ($k) => in_array($k, ['section', 'comments'], true) ? ($k === 'section' ? __('workstudy.cost.total') : null) : array_sum(array_map(fn ($r) => (float) $r[array_search($k, $keys, true)], $rows)), $keys) : null;

        return [__('workstudy.sp.trans.title'), $columns, $rows, [__('workstudy.cost.day.date') => $date], $total, "section-profit-loss-{$date}"];
    }

    /* ---------------- helpers ---------------- */

    private function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    private function period(mixed $period): string
    {
        if (! is_string($period) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw ValidationException::withMessages(['period' => __('workstudy.cost.v.period')]);
        }

        return $period;
    }

    private function date(mixed $date): string
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
            throw ValidationException::withMessages(['date' => __('workstudy.sp.v.date')]);
        }

        return Carbon::parse($date)->toDateString();
    }
}
