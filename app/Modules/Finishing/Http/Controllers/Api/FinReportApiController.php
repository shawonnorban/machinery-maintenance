<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Http\Controllers\Api;

use App\Modules\Finishing\Support\FinReports;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Finishing Line → Fabric Finishing Reports (AnnotechERP 18/06): the boxes
 * the report screen offers, and one report read with them. Nothing here
 * changes a record.
 */
class FinReportApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('finishing.report.view');
        $c = $this->caller()->companyId;

        return ApiResponse::ok([
            'company' => ['value' => $c, 'label' => DB::table('companies')->where('id', $c)->value('name')],
            'stores' => DB::table('stores')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->all(),
            'parties' => DB::table('mp_suppliers')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
        ]);
    }

    public function show(Request $request, string $report): JsonResponse
    {
        $this->allow('finishing.report.view');
        abort_unless(in_array($report, FinReports::REPORTS, true), 404);
        $c = $this->caller()->companyId;
        $f = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'store' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $c)],
            'party' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)],
        ], ['to.after_or_equal' => __('finishing.rpt.v.window')]);

        return ApiResponse::ok(['set' => 'finishing', 'report' => $report] + (new FinReports($f))->run($report) + ['pending_module' => FinReports::PENDING[$report] ?? null]);
    }
}
