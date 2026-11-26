<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Http\Controllers\Api;

use App\Modules\HrPayroll\Support\HrDashboardBook;
use App\Modules\HrPayroll\Support\HrReportBook;
use App\Modules\HrPayroll\Support\LetterBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * HR & Payroll → Dashboard and Reports (AnnotechERP 24/02).
 *
 * - **Dashboard:** head count, today's attendance, departments, the month's
 *   attendance and payroll, recent joiners, separations waiting, lefty cases,
 *   notifications, and Today's Operations (for the day or the month to it).
 * - **Reports:** the 47 reports in six categories; View answers the grid,
 *   Print / PDF draw it on the letterhead in the browser, Excel downloads it.
 *   Salary reports also need the salary view permission.
 */
class HrReportApiController extends ApiController
{
    private const FILTERS = ['date', 'from', 'to', 'month', 'period', 'fiscalYear', 'festival', 'department', 'section', 'line', 'floor', 'designation', 'shift', 'grade', 'otType', 'structure', 'employee', 'status'];

    public function dashboard(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.dashboard.view');
        $d = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'period' => ['nullable', Rule::in(['TODAY', 'MONTH'])], 'department' => ['nullable', 'string', 'max:64']]);
        $caller = $this->caller();
        $c = $caller->companyId;
        $date = $d['date'] ?? CarbonImmutable::now(HrReportBook::TZ)->toDateString();
        // Line-wise manpower opens on Sewing, as Brickland's does, else the biggest department.
        $department = $d['department'] ?? DB::table('departments')->where('company_id', $c)->whereRaw('lower(name) = ?', ['sewing'])->value('id')
            ?? DB::table('hr_employees')->where('company_id', $c)->where('status', 'ACTIVE')->whereNotNull('department_id')->groupBy('department_id')->orderByRaw('count(*) desc')->value('department_id');

        return ApiResponse::ok((new HrDashboardBook($c))->data($date, $d['period'] ?? 'TODAY', $department, $caller->can('hrpayroll.salary.view'), $caller->user?->id) + [
            'can' => ['ot' => $caller->can('hrpayroll.ot.view'), 'leave' => $caller->can('hrpayroll.leave.view'), 'attendance' => $caller->can('hrpayroll.attendance.view'),
                'separation' => $caller->can('hrpayroll.separation.view'), 'employee' => $caller->can('hrpayroll.employee.view'), 'salary' => $caller->can('hrpayroll.salary.view')],
        ]);
    }

    public function catalog(): JsonResponse
    {
        $this->allowEither('hrpayroll.report.view', 'hrpayroll.report.export');

        return ApiResponse::ok((new HrReportBook($this->caller()->companyId))->catalog() + [
            'canView' => true, 'canExport' => $this->caller()->can('hrpayroll.report.export'), 'canSalary' => $this->caller()->can('hrpayroll.salary.view'),
            'letterhead' => (new LetterBook($this->caller()->companyId))->letterhead(), 'by' => $this->caller()->user?->name,
        ]);
    }

    public function run(Request $request, string $key): JsonResponse
    {
        $this->allowEither('hrpayroll.report.view', 'hrpayroll.report.export');

        return ApiResponse::ok($this->report($request, $key));
    }

    /** The report as an Excel sheet: the title, the filters, the header, the rows and the total. */
    public function excel(Request $request, string $key): Response
    {
        $this->allow('hrpayroll.report.export');
        $r = $this->report($request, $key);
        $name = __('hrpayroll.rp.r.'.$key);

        return response()->streamDownload(function () use ($r, $name): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues([$name]));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(array_merge(['#'], array_map(fn ($c) => $c['label'], $r['columns']))));
            foreach ($r['rows'] as $i => $row) {
                $writer->addRow(Row::fromValues(array_merge([$i + 1], array_map(fn ($c) => $row[$c['key']] ?? '', $r['columns']))));
            }
            if ($r['foot']) {
                $writer->addRow(Row::fromValues(array_merge([''], array_map(fn ($c) => $r['foot'][$c['key']] ?? '', $r['columns']))));
            }
            $writer->close();
        }, str_replace(['/', ' '], ['-', '-'], $name).'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return array<string, mixed> */
    private function report(Request $request, string $key): array
    {
        $def = HrReportBook::find($key);
        abort_unless($def !== null, 404);
        if ($def['cat'] === 'salary' && ! $this->caller()->can('hrpayroll.salary.view')) {
            $this->allow('hrpayroll.salary.view');
        }
        $f = $request->validate(array_merge(array_fill_keys(self::FILTERS, ['nullable', 'string', 'max:120']), [
            'date' => ['nullable', 'date_format:Y-m-d'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'], 'month' => ['nullable', 'date_format:Y-m'],
        ]));
        $f = array_intersect_key($f, array_flip(array_merge($def['filters'], ['date', 'from', 'to', 'month', 'period', 'fiscalYear'])));
        $out = (new HrReportBook($this->caller()->companyId))->run($key, $f);
        $out['rows'] = array_map(fn ($row) => array_filter($row, fn ($k) => ! str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY), $out['rows']);

        return $out;
    }
}
