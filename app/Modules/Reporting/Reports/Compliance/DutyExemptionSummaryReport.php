<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Compliance;

use App\Modules\Compliance\Models\DutyAssessment;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Duty that fell due on goods leaving the bond (Shared Systems §3.5).
 *
 * The exemption is worth exactly what was never charged; this is the
 * other side of it — what the factory lost the exemption on, and why.
 * `reason` is the column that matters: a local sale is a commercial
 * decision, while a shortage at stock count is stock nobody can account
 * for, and the second is what customs asks about.
 *
 * Assessments that found no tariff heading are included and flagged. An
 * unrated release is a gap in the item library, and leaving it out of the
 * summary is how it stays a gap.
 */
class DutyExemptionSummaryReport extends Report
{
    public function key(): string
    {
        return 'compliance_duty_summary';
    }

    public function group(): string
    {
        return 'compliance';
    }

    public function permission(): string
    {
        return 'compliance.bond.view_any';
    }

    public function filters(): array
    {
        return ['search', 'period', 'factory'];
    }

    public function columns(): array
    {
        return [
            'assessment_no' => ['label' => 'report.columns.assessment_no'],
            'assessment_date' => ['label' => 'report.columns.assessment_date'],
            'licence_no' => ['label' => 'report.columns.licence_no'],
            'item_code' => ['label' => 'report.columns.item_code'],
            'item_name' => ['label' => 'report.columns.name'],
            'reason' => ['label' => 'report.columns.reason'],
            'store' => ['label' => 'report.columns.store'],
            'quantity' => ['label' => 'report.columns.quantity', 'numeric' => true],
            'assessable_value' => ['label' => 'report.columns.assessable_value', 'numeric' => true],
            'hs_code' => ['label' => 'report.columns.hs_code'],
            'duty_amount' => ['label' => 'report.columns.duty', 'numeric' => true],
            'vat_amount' => ['label' => 'report.columns.vat', 'numeric' => true],
            'total_payable' => ['label' => 'report.columns.total_payable', 'numeric' => true],
            'rated' => ['label' => 'report.columns.rated'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $assessment) {
            yield [
                'assessment_no' => $assessment->assessment_no,
                'assessment_date' => $assessment->assessment_date?->toDateString(),
                'licence_no' => $assessment->licence?->licence_no,
                'item_code' => $assessment->item_code,
                'item_name' => $assessment->item_name,
                'reason' => __("compliance.duty_reasons.{$assessment->reason}"),
                'store' => $assessment->store?->name,
                'quantity' => (string) $assessment->quantity,
                'assessable_value' => (string) $assessment->assessable_value,
                'hs_code' => $assessment->hs_code,
                'duty_amount' => (string) $assessment->duty_amount,
                'vat_amount' => (string) $assessment->vat_amount,
                'total_payable' => (string) $assessment->total_payable,
                // A gap in the item library, shown rather than hidden.
                'rated' => $assessment->isRated() ? __('common.yes') : __('common.no'),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = DutyAssessment::query()
            ->with(['licence:id,licence_no', 'store:id,name'])
            ->whereBetween('assessment_date', [$query->from->toDateString(), $query->to->toDateString()]);

        if ($query->factoryId !== null) {
            $builder->where('factory_id', $query->factoryId);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $builder->where(fn ($q) => $q->whereRaw('LOWER(assessment_no) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(item_code) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(item_name) LIKE ?', [$needle]));
        }

        return $builder->orderByDesc('assessment_date');
    }
}
