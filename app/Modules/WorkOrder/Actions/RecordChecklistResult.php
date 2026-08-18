<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Actions;

use App\Modules\Maintenance\Models\ChecklistItem;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderChecklistResult;
use App\Shared\Files\Models\FileAttachment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records one answer on an executed checklist (SRS 12, ERD Section 9 rules 1-4).
 *
 * The four rules here are the reason a checklist is worth anything in an audit:
 * a failure that demands a note carries one, a failure that demands a photo
 * carries one, a reading outside tolerance is a failure whatever the technician
 * tapped, and a failure that should raise corrective work raises it. Leave any
 * of them to the interface and the record becomes a record of what the form
 * allowed, not of what the machine was like.
 */
class RecordChecklistResult
{
    public function __construct(private readonly CreateWorkOrder $createWorkOrder) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(WorkOrder $workOrder, array $data, ?string $userId = null): WorkOrderChecklistResult
    {
        // Answers belong to work in progress. Before it starts there is nothing
        // to observe; after it completes the record is what was certified, and a
        // later edit would rewrite it silently.
        if ($workOrder->status !== 'IN_PROGRESS') {
            throw ValidationException::withMessages([
                'work_order_id' => __('work_order.checklist_needs_in_progress'),
            ])->status(409);
        }

        $item = $this->resolveItem($workOrder, (string) ($data['checklist_item_id'] ?? ''));

        $result = (string) ($data['result'] ?? '');

        if (! in_array($result, WorkOrderChecklistResult::RESULTS, true)) {
            throw ValidationException::withMessages([
                'result' => __('work_order.checklist_result_unknown'),
            ]);
        }

        if ($result === 'NA' && $item->required) {
            // "Not applicable" on a required item is how a checklist gets
            // emptied without anyone skipping anything.
            throw ValidationException::withMessages([
                'result' => __('work_order.checklist_required_not_na'),
            ]);
        }

        $numeric = $this->normaliseNumeric($item, $data, $result);
        $text = $this->normaliseText($item, $data, $result);

        $withinTolerance = $item->isWithinTolerance($numeric);

        // Rule 3. The reading is the observation; the pass/fail beside it is an
        // opinion, and a measured 9.4 bar against a 6-8 bar tolerance is a
        // failure even if the technician ticked pass out of habit.
        if ($withinTolerance === false) {
            $result = 'FAIL';
        }

        $observation = trim((string) ($data['observation'] ?? '')) ?: null;
        $fileId = $this->resolveFileId($data['file_id'] ?? null, $workOrder);

        if ($result === 'FAIL') {
            // Rule 1.
            if ($item->requires_note_on_fail && $observation === null) {
                throw ValidationException::withMessages([
                    'observation' => __('work_order.checklist_fail_needs_note', ['item' => $item->label]),
                ]);
            }

            if ($item->requires_attachment_on_fail && $fileId === null) {
                throw ValidationException::withMessages([
                    'file_id' => __('work_order.checklist_fail_needs_photo', ['item' => $item->label]),
                ]);
            }
        }

        return DB::transaction(function () use (
            $workOrder, $item, $result, $numeric, $text, $observation,
            $fileId, $withinTolerance, $userId
        ): WorkOrderChecklistResult {
            // An answer is corrected, not duplicated: the unique key is the
            // work order and the item, so a technician fixing a mistyped
            // reading overwrites it rather than leaving two truths.
            $record = WorkOrderChecklistResult::updateOrCreate(
                ['work_order_id' => $workOrder->id, 'checklist_item_id' => $item->id],
                [
                    'result' => $result,
                    'numeric_value' => $numeric,
                    'text_value' => $text,
                    'observation' => $observation,
                    'file_id' => $fileId,
                    'is_within_tolerance' => $withinTolerance,
                    'completed_by' => $userId,
                    'completed_at' => CarbonImmutable::now(),
                ],
            );

            // Rule 2.
            if ($result === 'FAIL'
                && $item->fail_creates_followup_work_order
                && $record->followup_work_order_id === null) {
                $followUp = $this->raiseFollowUp($workOrder, $item, $observation, $userId);

                $record->forceFill(['followup_work_order_id' => $followUp->id])->save();
            }

            // A corrected answer that is no longer a failure leaves the
            // follow-up alone. Cancelling it would need a reason and a decision
            // that is not this action's to make, and silently deleting
            // corrective work is exactly how a fault gets forgotten.

            return $record->fresh();
        });
    }

    /**
     * The item must come from the version this work order froze. Accepting any
     * item id would let an answer from a different checklist, or a different
     * tenant's checklist, be filed against this job.
     */
    private function resolveItem(WorkOrder $workOrder, string $itemId): ChecklistItem
    {
        if ($workOrder->template_version_id === null) {
            throw ValidationException::withMessages([
                'checklist_item_id' => __('work_order.checklist_no_version'),
            ])->status(409);
        }

        $item = ChecklistItem::where('id', $itemId)
            ->where('template_version_id', $workOrder->template_version_id)
            ->first();

        if ($item === null) {
            throw ValidationException::withMessages([
                'checklist_item_id' => __('work_order.checklist_item_not_on_version'),
            ]);
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function normaliseNumeric(ChecklistItem $item, array $data, string $result): ?string
    {
        $raw = $data['numeric_value'] ?? null;

        if (! $item->isNumeric()) {
            // A number against a pass/fail item has nowhere to be read from
            // again, so it is dropped rather than stored where nothing looks.
            return null;
        }

        if ($result === 'NA') {
            return null;
        }

        if ($raw === null || $raw === '') {
            throw ValidationException::withMessages([
                'numeric_value' => __('work_order.checklist_needs_reading', ['item' => $item->label]),
            ]);
        }

        if (! is_numeric($raw)) {
            throw ValidationException::withMessages([
                'numeric_value' => __('work_order.checklist_reading_not_numeric'),
            ]);
        }

        return number_format((float) $raw, 4, '.', '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function normaliseText(ChecklistItem $item, array $data, string $result): ?string
    {
        $raw = trim((string) ($data['text_value'] ?? '')) ?: null;

        if ($result === 'NA') {
            return $raw;
        }

        if ($item->input_type === 'TEXT' && $raw === null) {
            throw ValidationException::withMessages([
                'text_value' => __('work_order.checklist_needs_text', ['item' => $item->label]),
            ]);
        }

        if ($item->input_type === 'CHOICE') {
            $options = $item->options_json ?? [];

            if ($raw === null || ! in_array($raw, $options, true)) {
                // Free text against a fixed list makes the column unreportable,
                // which is the only reason the list exists.
                throw ValidationException::withMessages([
                    'text_value' => __('work_order.checklist_choice_invalid', [
                        'item' => $item->label,
                        'options' => implode(', ', $options),
                    ]),
                ]);
            }
        }

        return $raw;
    }

    /**
     * A file id is only accepted if it is this tenant's and it was uploaded
     * against this work order. Otherwise one job's photo could be presented as
     * evidence on another.
     */
    private function resolveFileId(mixed $fileId, WorkOrder $workOrder): ?string
    {
        if (blank($fileId)) {
            return null;
        }

        $file = FileAttachment::where('id', $fileId)
            ->where('attachable_type', 'work_order')
            ->where('attachable_id', $workOrder->id)
            ->first();

        if ($file === null) {
            throw ValidationException::withMessages([
                'file_id' => __('work_order.checklist_file_not_found'),
            ]);
        }

        return $file->id;
    }

    /**
     * Corrective work raised by a failed check. It carries the observation
     * across, because the person who picks it up needs to know what was seen,
     * and it is linked both ways so neither record loses the other.
     */
    private function raiseFollowUp(
        WorkOrder $workOrder,
        ChecklistItem $item,
        ?string $observation,
        ?string $userId,
    ): WorkOrder {
        return $this->createWorkOrder->handle([
            'asset_id' => $workOrder->asset_id,
            'maintenance_type_id' => $this->correctiveTypeId($workOrder),
            'title' => __('work_order.followup_title', [
                'item' => mb_substr($item->label, 0, 120),
            ]),
            'description' => __('work_order.followup_description', [
                'number' => $workOrder->work_order_number,
                'item' => $item->label,
                'observation' => $observation ?? __('work_order.followup_no_note'),
            ]),
            // A safety item that failed is not routine work.
            'priority' => $item->is_safety_item ? 'CRITICAL' : 'HIGH',
            'source' => 'CHECKLIST_FAILURE',
            'currency' => $workOrder->currency,
        ], $userId);
    }

    /**
     * Corrective by preference, falling back to the parent's type. A follow-up
     * with no maintenance type cannot be created at all, and refusing to record
     * the failure because the corrective type is missing would be the worse
     * outcome of the two.
     */
    private function correctiveTypeId(WorkOrder $workOrder): string
    {
        $corrective = MaintenanceType::query()
            ->availableTo($workOrder->company_id)
            ->where('code', 'CORRECTIVE')
            ->where('active', true)
            ->first();

        return $corrective?->id ?? $workOrder->maintenance_type_id;
    }
}
