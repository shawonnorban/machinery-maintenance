<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Resources;

use App\Modules\FabricPlanning\Models\FpReport;
use App\Modules\FabricPlanning\Models\FpReportRun;
use App\Modules\FabricPlanning\Support\FpOps;
use App\Modules\FabricPlanning\Support\FpReports;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Fabric Requirement Reports → Records and New report run: a report run with
 * the unit, buyer and week it covered — blank is All — and how many rows the
 * report gave when it was saved. Fabric Requirement → Reports (AnnotechERP 13/03).
 */
class ReportRunResource extends FabricOpsResource
{
    public function key(): string
    {
        return 'fp-report-runs';
    }

    public function model(): string
    {
        return FpReportRun::class;
    }

    public function viewPermission(): string
    {
        return 'fabricplanning.report.view';
    }

    public function managePermission(): string
    {
        return 'fabricplanning.report.view';
    }

    public function columns(): array
    {
        return ['report_id', 'factory_id', 'buyer_id', 'week_start', 'export_excel', 'rows'];
    }

    public function booleans(): array
    {
        return ['export_excel'];
    }

    public function query(): Builder
    {
        FpReports::ensure($this->company());

        return FpReportRun::query()->latest('created_at');
    }

    public function messages(): array
    {
        return ['report_id.required' => __('fabricplanning.ops.v.report')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'report_id' => [$required, Rule::exists('fp_reports', 'id')->where('company_id', $this->company())->where('active', true)],
            'factory_id' => ['nullable', Rule::exists('factories', 'id')->where('company_id', $this->company())],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $this->company())],
            'week_start' => ['nullable', 'date'],
            'export_excel' => ['sometimes', 'boolean'],
        ];
    }

    /** The week sits on its Saturday; the rows are counted from the report as it stands now. */
    public function beforeSave(array $data, ?Model $row): array
    {
        unset($data['rows']);
        if (! empty($data['week_start'])) {
            $data['week_start'] = FpOps::weekStart((string) $data['week_start'])->toDateString();
        }
        $n = array_merge($row?->only(['report_id', 'factory_id', 'buyer_id']) ?? [], ['week_start' => $row?->getAttribute('week_start')?->toDateString()], $data);
        $code = FpReport::query()->whereKey($n['report_id'])->value('code');
        $data['rows'] = count(FpReports::rows((string) $code, ['factory_id' => $n['factory_id'] ?? null, 'buyer_id' => $n['buyer_id'] ?? null, 'week_start' => $n['week_start'] ?? null]));
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $report = FpReport::query()->find($row->getAttribute('report_id'));
        $out['report'] = $report?->name;
        $out['code'] = $report?->code;
        $out['category'] = $report?->category;
        $out['week'] = $row->getAttribute('week_start') ? FpOps::weekLabel($row->getAttribute('week_start')->toDateString()) : null;

        return $out;
    }
}
