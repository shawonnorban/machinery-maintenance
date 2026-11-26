<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Http\Controllers\Api;

use App\Modules\FabricPlanning\Models\FpReport;
use App\Modules\FabricPlanning\Support\FpReports;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fabric Requirement → Reports (AnnotechERP 13/03): the catalogue, a report's
 * preview read live from the module's tables, and its rows as a workbook.
 * A report travels by its code (FP-MACHINE-LOAD), never its key.
 */
class FpReportsApiController extends ApiController
{
    public function catalogue(): JsonResponse
    {
        $this->allow('fabricplanning.report.view');
        FpReports::ensure(app(TenantContext::class)->companyId());

        return ApiResponse::ok(FpReport::query()->where('active', true)->orderBy('sort_order')->get()
            ->map(fn (FpReport $r) => ['id' => $r->id, 'code' => $r->code, 'name' => $r->name, 'category' => $r->category])->values()->all());
    }

    public function preview(string $code): JsonResponse
    {
        $this->allow('fabricplanning.report.view');
        $report = $this->report($code);

        return ApiResponse::ok(['code' => $report->code, 'name' => $report->name, 'columns' => FpReports::COLUMNS[$report->code] ?? [], 'rows' => FpReports::rows($report->code)]);
    }

    public function excel(string $code): StreamedResponse
    {
        $this->allow('fabricplanning.report.view');
        $report = $this->report($code);
        $columns = FpReports::COLUMNS[$report->code] ?? [];
        $rows = FpReports::rows($report->code);

        return response()->streamDownload(function () use ($columns, $rows): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn ($k) => __("fabricplanning.ops.rpt.col.{$k}"), $columns)));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($k) => $r[$k] ?? '', $columns)));
            }
            $writer->close();
        }, strtolower($report->code).'-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function report(string $code): FpReport
    {
        FpReports::ensure(app(TenantContext::class)->companyId());

        return FpReport::query()->where('code', strtoupper($code))->where('active', true)->firstOrFail();
    }
}
