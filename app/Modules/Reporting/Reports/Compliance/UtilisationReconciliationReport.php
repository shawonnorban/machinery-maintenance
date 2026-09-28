<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Compliance;

use App\Modules\Compliance\Models\UtilisationDeclaration;
use App\Modules\Compliance\Services\BondUtilisation;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;

/**
 * UD/UP against actual consumption (Shared Systems spec §3.5, §3.2).
 *
 * The variance is **signed**, and that is the point of the report:
 * consuming far less than declared leaves bonded goods with no export to
 * justify them, while consuming more is a shortfall somebody has to
 * account for. An absolute figure would make a factory sitting on unused
 * bonded fabric look identical to one that has over-drawn, and those are
 * opposite problems with opposite remedies.
 */
class UtilisationReconciliationReport extends Report
{
    public function __construct(private readonly BondUtilisation $utilisation) {}

    public function key(): string
    {
        return 'compliance_ud_reconciliation';
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
            'ud_no' => ['label' => 'report.columns.ud_no'],
            'ud_type' => ['label' => 'report.columns.ud_type'],
            'licence_no' => ['label' => 'report.columns.licence_no'],
            'issue_date' => ['label' => 'report.columns.issue_date'],
            'item_code' => ['label' => 'report.columns.item_code'],
            'item_name' => ['label' => 'report.columns.name'],
            'declared_quantity' => ['label' => 'report.columns.declared', 'numeric' => true],
            'wastage_percent' => ['label' => 'report.columns.wastage_percent', 'numeric' => true],
            'allowed_quantity' => ['label' => 'report.columns.allowed', 'numeric' => true],
            'consumed_quantity' => ['label' => 'report.columns.consumed_qty', 'numeric' => true],
            'variance_quantity' => ['label' => 'report.columns.variance', 'numeric' => true],
            'exceeds_allowance' => ['label' => 'report.columns.over_allowance'],
            'status' => ['label' => 'report.columns.status'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->declarations($query) as $declaration) {
            foreach ($this->utilisation->reconcile($declaration) as $row) {
                yield [
                    'ud_no' => $declaration->ud_no,
                    'ud_type' => $declaration->ud_type,
                    'licence_no' => $declaration->licence?->licence_no,
                    'issue_date' => $declaration->issue_date?->toDateString(),
                    'item_code' => $row['item_code'],
                    'item_name' => $row['item_name'],
                    'declared_quantity' => $row['declared_quantity'],
                    'wastage_percent' => $row['wastage_percent'],
                    'allowed_quantity' => $row['allowed_quantity'],
                    'consumed_quantity' => $row['consumed_quantity'],
                    // Signed — see the class note.
                    'variance_quantity' => $row['variance_quantity'],
                    'exceeds_allowance' => $row['exceeds_allowance'] ? __('common.yes') : __('common.no'),
                    'status' => __("compliance.declaration_statuses.{$declaration->status}"),
                ];
            }
        }
    }

    public function estimatedRows(ReportQuery $query): int
    {
        return $this->declarations($query)->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, UtilisationDeclaration>
     */
    private function declarations(ReportQuery $query)
    {
        $builder = UtilisationDeclaration::query()
            ->with(['licence:id,licence_no', 'lines.uom:id,code'])
            // A cancelled declaration was never in force, so there is
            // nothing to reconcile it against.
            ->where('status', '!=', 'CANCELLED')
            ->whereBetween('issue_date', [$query->from->toDateString(), $query->to->toDateString()]);

        if ($query->factoryId !== null) {
            $builder->where('factory_id', $query->factoryId);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $builder->whereRaw('LOWER(ud_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        return $builder->orderByDesc('issue_date')->get();
    }
}
