<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\WorkStudy\Http\Controllers\Api\WsReportApiController;
use App\Modules\WorkStudy\Models\WsReportRun;
use App\Modules\WorkStudy\Support\WsReports;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Work Study Reports → Records and Preview (New report run): a report run
 * for a unit, buyer, floor, style and production date. The run reads the
 * report for that day on save and keeps how many rows it found — a report
 * that waits for another module finds none. Work Study → Reports (AnnotechERP 14/02).
 */
class ReportRunResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'ws-report-runs';
    }

    public function model(): string
    {
        return WsReportRun::class;
    }

    public function viewPermission(): string
    {
        return 'workstudy.report.view';
    }

    public function managePermission(): string
    {
        return 'workstudy.report.view';
    }

    public function columns(): array
    {
        return ['report_key', 'factory_id', 'buyer_id', 'floor_id', 'order_no', 'production_date', 'export_excel'];
    }

    public function booleans(): array
    {
        return ['export_excel'];
    }

    public function query(): Builder
    {
        return WsReportRun::query()->orderByDesc('created_at');
    }

    public function messages(): array
    {
        return ['report_key.required' => __('workstudy.rp.v.report'), 'production_date.required' => __('workstudy.rp.v.date')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();

        return [
            'report_key' => [$required, Rule::in(array_keys(WsReports::REPORTS))],
            'factory_id' => ['nullable', Rule::exists('factories', 'id')->where('company_id', $company)],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'floor_id' => ['nullable', Rule::exists('floors', 'id')->where('company_id', $company)],
            'order_no' => ['nullable', 'string', 'max:64'],
            'production_date' => [$required, 'date'],
            'export_excel' => ['boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $value = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        $date = substr((string) ($value('production_date') instanceof \DateTimeInterface ? $value('production_date')->format('Y-m-d') : $value('production_date')), 0, 10);
        [$key, $filters] = WsReportApiController::filtersFor((string) $value('report_key'), [
            'date' => $date, 'from' => $date, 'to' => $date,
            'office' => $value('factory_id') ? DB::table('factories')->where('id', $value('factory_id'))->value('code') : null,
            'buyer' => $value('buyer_id') ? DB::table('buyers')->where('id', $value('buyer_id'))->value('code') : null,
            'floors' => $value('floor_id') ? DB::table('floors')->where('id', $value('floor_id'))->value('code') : null,
            'order' => $value('order_no'),
        ]);
        $data['rows_count'] = count(WsReports::run($key, $filters)['rows']);
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        /** @var WsReportRun $row */
        $out = parent::present($row);
        $out['production_date'] = $row->production_date?->toDateString();
        $out['report'] = __("workstudy.rp.report.{$row->report_key}");
        $out['category'] = WsReports::REPORTS[$row->report_key][0] ?? null;
        $out['unit'] = $row->factory_id ? DB::table('factories')->where('id', $row->factory_id)->value('code') : __('workstudy.rp.all');
        $out['rows_count'] = (int) $row->rows_count;

        return $out;
    }
}
