<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Imports\Types;

use App\Modules\Asset\Models\Asset;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Reporting\Imports\ImportColumn;
use App\Modules\Reporting\Imports\ImportContext;
use App\Modules\Reporting\Imports\Importer;
use App\Modules\Reporting\Imports\ImportOutcome;
use App\Modules\Reporting\Imports\PreparedRow;
use App\Modules\Reporting\Imports\RowContext;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderStatusHistory;
use App\Shared\Support\TenantTimezone;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Work already done, from whatever the factory was using before (SRS 33).
 *
 * Without this, a factory arriving on the system starts with machines that have
 * no history, and every reliability figure reads as though the fleet was
 * installed yesterday. MTBF in particular is meaningless until there is a past.
 *
 * These records are created closed, in one step, and that is a deliberate
 * exception to the work order state machine. Replaying transitions over
 * imported history would write status changes that never happened at times
 * nobody recorded — a fabricated audit trail is worse than a thin one. Each
 * imported order is marked is_imported with source IMPORT, so a report can tell
 * measured history from declared history.
 *
 * Timestamps are read on the factory clock. A maintenance log kept in Dhaka
 * says 14:30 meaning Dhaka's afternoon, and storing it as 14:30 UTC would move
 * every entry six hours and quietly corrupt the response times derived from it.
 */
class MaintenanceHistoryImporter extends Importer
{
    public function __construct(
        private readonly CreateWorkOrder $create,
        private readonly TenantTimezone $timezone,
        private readonly TenantContext $tenant,
    ) {}

    public function type(): string
    {
        return 'maintenance_history';
    }

    public function permission(): string
    {
        return 'work_order.work_order.create';
    }

    public function columns(): array
    {
        return [
            'asset_code' => new ImportColumn('import.columns.asset_code', true, 'SEW-DHK-00412'),
            'title' => new ImportColumn('import.columns.title', true, 'Quarterly service'),
            'maintenance_type_code' => new ImportColumn('import.columns.maintenance_type_code', true, 'PREVENTIVE'),
            'completed_at' => new ImportColumn('import.columns.completed_at', true, '2025-11-14 16:30'),
            'started_at' => new ImportColumn('import.columns.started_at', false, '2025-11-14 14:30'),
            'description' => new ImportColumn('import.columns.description', false, 'Belt and needle plate replaced'),
            'parts_cost' => new ImportColumn('import.columns.parts_cost', false, '2450'),
            'other_cost' => new ImportColumn('import.columns.other_cost', false, '0'),
            'currency' => new ImportColumn('import.columns.currency', false, 'BDT'),
            'external_reference' => new ImportColumn('import.columns.external_reference', false, 'LOG-2025-1142'),
        ];
    }

    public function prepare(array $row, RowContext $context): PreparedRow
    {
        $errors = [];

        foreach (['asset_code', 'title', 'maintenance_type_code', 'completed_at'] as $required) {
            if (($row[$required] ?? null) === null) {
                $errors[] = ['field' => $required, 'error' => __('import.errors.required'), 'value' => null];
            }
        }

        if ($errors !== []) {
            return PreparedRow::invalid($context->rowNumber, $errors, $row);
        }

        $asset = $context->remember("asset:{$row['asset_code']}", fn () => Asset::query()
            ->where('asset_code', $row['asset_code'])->first());

        if ($asset === null) {
            $errors[] = [
                'field' => 'asset_code',
                'error' => __('import.errors.unknown_reference'),
                'value' => $row['asset_code'],
            ];
        }

        $type = $context->remember("type:{$row['maintenance_type_code']}", fn () => MaintenanceType::availableTo($this->tenant->companyId())
            ->where('code', $row['maintenance_type_code'])->first());

        if ($type === null) {
            $errors[] = [
                'field' => 'maintenance_type_code',
                'error' => __('import.errors.unknown_reference'),
                'value' => $row['maintenance_type_code'],
            ];
        }

        $completedAt = $this->instant($row['completed_at']);
        $startedAt = $row['started_at'] === null ? null : $this->instant($row['started_at']);

        if ($completedAt === null) {
            $errors[] = [
                'field' => 'completed_at',
                'error' => __('import.errors.datetime_format'),
                'value' => $row['completed_at'],
            ];
        }

        if ($row['started_at'] !== null && $startedAt === null) {
            $errors[] = [
                'field' => 'started_at',
                'error' => __('import.errors.datetime_format'),
                'value' => $row['started_at'],
            ];
        }

        if ($completedAt !== null && $startedAt !== null && $startedAt->greaterThan($completedAt)) {
            $errors[] = [
                'field' => 'started_at',
                'error' => __('import.errors.started_after_completed'),
                'value' => $row['started_at'],
            ];
        }

        if ($completedAt !== null && $completedAt->isFuture()) {
            // History, by definition. A future date here is a typo in the year
            // column, and importing it would put maintenance that has not
            // happened into a compliance figure.
            $errors[] = [
                'field' => 'completed_at',
                'error' => __('import.errors.not_in_the_past'),
                'value' => $row['completed_at'],
            ];
        }

        foreach (['parts_cost', 'other_cost'] as $numeric) {
            if ($row[$numeric] !== null && ! is_numeric(str_replace(',', '', $row[$numeric]))) {
                $errors[] = [
                    'field' => $numeric,
                    'error' => __('import.errors.numeric'),
                    'value' => $row[$numeric],
                ];
            }
        }

        if ($errors !== []) {
            return PreparedRow::invalid($context->rowNumber, $errors, $row);
        }

        return PreparedRow::valid($context->rowNumber, [
            'asset_id' => $asset->id,
            'maintenance_type_id' => $type->id,
            'title' => $row['title'],
            'description' => $row['description'],
            'actual_start' => ($startedAt ?? $completedAt)->toIso8601String(),
            'completed_at' => $completedAt->toIso8601String(),
            'parts_cost' => $this->number($row['parts_cost']),
            'other_cost' => $this->number($row['other_cost']),
            'currency' => $row['currency'] ?? 'BDT',
            'external_reference' => $row['external_reference'],
        ], $row);
    }

    public function write(PreparedRow $row, ImportContext $context): ImportOutcome
    {
        try {
            return DB::transaction(function () use ($row, $context): ImportOutcome {
                // Through the action, so numbering, factory resolution and the
                // asset checks are the same ones the screens apply (ADR-066).
                $workOrder = $this->create->handle([
                    'asset_id' => $row->values['asset_id'],
                    'maintenance_type_id' => $row->values['maintenance_type_id'],
                    'title' => $row->values['title'],
                    'description' => $row->values['description'],
                    'source' => 'IMPORT',
                ], $context->userId);

                $parts = $row->values['parts_cost'] ?? '0';
                $other = $row->values['other_cost'] ?? '0';

                $workOrder->forceFill([
                    'status' => 'CLOSED',
                    'actual_start' => $row->values['actual_start'],
                    'actual_end' => $row->values['completed_at'],
                    'completed_at' => $row->values['completed_at'],
                    'completed_by' => $context->userId,
                    'closed_at' => $row->values['completed_at'],
                    'closed_by' => $context->userId,
                    'actual_parts_cost' => $parts,
                    'actual_other_cost' => $other,
                    'actual_cost' => bcadd((string) $parts, (string) $other, 4),
                    'currency' => $row->values['currency'],
                    'requires_verification' => false,
                    'is_imported' => true,
                ])->save();

                // One history row saying what actually happened: this record
                // arrived as history. Inventing SCHEDULED and IN_PROGRESS rows
                // at made-up times would be a fabricated audit trail.
                WorkOrderStatusHistory::create([
                    'work_order_id' => $workOrder->id,
                    'from_status' => 'DRAFT',
                    'to_status' => 'CLOSED',
                    'changed_by' => $context->userId,
                    'changed_at' => CarbonImmutable::now(),
                    'reason' => __('import.history_reason', [
                        'reference' => $row->values['external_reference'] ?? $context->batchId,
                    ]),
                ]);

                return ImportOutcome::created();
            });
        } catch (ValidationException $e) {
            return ImportOutcome::failed(implode(' ', $e->validator->errors()->all()));
        } catch (Throwable $e) {
            return ImportOutcome::failed($e->getMessage());
        }
    }

    /**
     * A local date or date and time, read on the factory clock.
     */
    private function instant(string $value): ?CarbonImmutable
    {
        $value = trim($value);

        $matchesDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
        $matchesDateTime = preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $value) === 1;

        if (! $matchesDate && ! $matchesDateTime) {
            return null;
        }

        try {
            return $this->timezone->toUtc($matchesDate ? $value.' 00:00:00' : $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function number(?string $value): ?string
    {
        return $value === null ? null : str_replace(',', '', $value);
    }
}
