<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Compliance;

use App\Modules\Compliance\Models\BondLicence;
use App\Modules\Compliance\Services\BondUtilisation;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;

/**
 * Bond utilisation (Shared Systems spec §3.5).
 *
 * What each licence entitles the factory to import, against what has
 * actually come in. Both figures are computed from goods receipts rather
 * than from a running total, so the report cannot disagree with the
 * ledger it is drawn from.
 *
 * Importing past an entitlement is flagged rather than left as a negative
 * number in a column — it is the single thing a bond audit looks for, and
 * a minus sign among four hundred rows is not a finding anybody sees.
 */
class BondUtilisationReport extends Report
{
    public function __construct(private readonly BondUtilisation $utilisation) {}

    public function key(): string
    {
        return 'compliance_bond_utilisation';
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
        return ['search', 'factory'];
    }

    public function columns(): array
    {
        return [
            'licence_no' => ['label' => 'report.columns.licence_no'],
            'factory' => ['label' => 'report.columns.factory'],
            'valid_to' => ['label' => 'report.columns.valid_to'],
            'item_code' => ['label' => 'report.columns.item_code'],
            'item_name' => ['label' => 'report.columns.name'],
            'basis' => ['label' => 'report.columns.basis'],
            'entitled_quantity' => ['label' => 'report.columns.entitled', 'numeric' => true],
            'imported_quantity' => ['label' => 'report.columns.imported', 'numeric' => true],
            'remaining_quantity' => ['label' => 'report.columns.remaining', 'numeric' => true],
            'used_percent' => ['label' => 'report.columns.used_percent', 'numeric' => true],
            'exceeded' => ['label' => 'report.columns.over_entitlement'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->licences($query) as $licence) {
            foreach ($this->utilisation->forLicence($licence) as $row) {
                yield [
                    'licence_no' => $licence->licence_no,
                    'factory' => $licence->factory?->name,
                    'valid_to' => $licence->valid_to?->toDateString(),
                    'item_code' => $row['item_code'],
                    'item_name' => $row['item_name'],
                    'basis' => __("compliance.bases.{$row['basis']}"),
                    'entitled_quantity' => $row['entitled_quantity'],
                    'imported_quantity' => $row['imported_quantity'],
                    'remaining_quantity' => $row['remaining_quantity'],
                    'used_percent' => $row['used_percent'],
                    'exceeded' => $row['exceeded'] ? __('common.yes') : __('common.no'),
                ];
            }
        }
    }

    /** Licences in, entitlement rows out; the expansion is the report. */
    public function estimatedRows(ReportQuery $query): int
    {
        return $this->licences($query)->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, BondLicence>
     */
    private function licences(ReportQuery $query)
    {
        $builder = BondLicence::query()
            ->with(['factory:id,name', 'entitlements.uom:id,code'])
            ->where('status', '!=', 'CANCELLED');

        if ($query->factoryId !== null) {
            $builder->where('factory_id', $query->factoryId);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $builder->whereRaw('LOWER(licence_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        return $builder->orderBy('licence_no')->get();
    }
}
