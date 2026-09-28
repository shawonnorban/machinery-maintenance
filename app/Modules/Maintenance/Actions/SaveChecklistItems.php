<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\Models\ChecklistItem;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the item list of a DRAFT version.
 *
 * A published version is refused outright. Editing one would silently rewrite
 * what a technician certified they had checked, and every work order that
 * referenced it would start reporting a list nobody ever worked through
 * (SRS 12).
 */
class SaveChecklistItems
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function handle(MaintenanceTemplateVersion $version, array $items): MaintenanceTemplateVersion
    {
        if (! $version->isEditable()) {
            throw ValidationException::withMessages([
                'items' => __('maintenance.version_frozen', ['version' => $version->version_number]),
            ])->status(409);
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => __('maintenance.checklist_empty'),
            ]);
        }

        $this->assertValid($items);

        return DB::transaction(function () use ($version, $items): MaintenanceTemplateVersion {
            // Replace wholesale. The builder submits the whole list, and
            // diffing rows would leave orphans when an item is removed.
            $version->items()->delete();

            foreach (array_values($items) as $index => $item) {
                ChecklistItem::create([
                    'company_id' => $version->company_id,
                    'template_version_id' => $version->id,
                    // Sequence comes from submitted order, not from the client:
                    // a duplicate or gapped sequence would break the unique
                    // index and the execution screen's ordering.
                    'sequence' => $index + 1,
                    'label' => $item['label'],
                    'help_text' => $item['help_text'] ?? null,
                    'input_type' => $item['input_type'] ?? 'PASS_FAIL',
                    'unit' => $item['unit'] ?? null,
                    'options_json' => $item['options_json'] ?? null,
                    'expected_value' => $item['expected_value'] ?? null,
                    'tolerance_min' => $item['tolerance_min'] ?? null,
                    'tolerance_max' => $item['tolerance_max'] ?? null,
                    'required' => (bool) ($item['required'] ?? true),
                    'allows_attachment' => (bool) ($item['allows_attachment'] ?? true),
                    'requires_attachment_on_fail' => (bool) ($item['requires_attachment_on_fail'] ?? false),
                    'requires_note_on_fail' => (bool) ($item['requires_note_on_fail'] ?? false),
                    'fail_creates_followup_work_order' => (bool) ($item['fail_creates_followup_work_order'] ?? false),
                    'is_safety_item' => (bool) ($item['is_safety_item'] ?? false),
                ]);
            }

            return $version->fresh(['items']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function assertValid(array $items): void
    {
        foreach ($items as $index => $item) {
            $position = $index + 1;

            if (blank($item['label'] ?? null)) {
                throw ValidationException::withMessages([
                    "items.{$index}.label" => __('maintenance.item_label_required', ['position' => $position]),
                ]);
            }

            $type = $item['input_type'] ?? 'PASS_FAIL';

            if (! in_array($type, ChecklistItem::INPUT_TYPES, true)) {
                throw ValidationException::withMessages([
                    "items.{$index}.input_type" => __('maintenance.item_type_unknown', ['position' => $position]),
                ]);
            }

            // A tolerance on a pass/fail item is meaningless and would be
            // silently ignored at execution, which is worse than refusing it.
            $hasTolerance = filled($item['tolerance_min'] ?? null) || filled($item['tolerance_max'] ?? null);

            if ($hasTolerance && $type !== 'NUMERIC') {
                throw ValidationException::withMessages([
                    "items.{$index}.tolerance_min" => __('maintenance.tolerance_needs_numeric', ['position' => $position]),
                ]);
            }

            if ($hasTolerance
                && filled($item['tolerance_min'] ?? null)
                && filled($item['tolerance_max'] ?? null)
                && (float) $item['tolerance_min'] > (float) $item['tolerance_max']) {
                throw ValidationException::withMessages([
                    "items.{$index}.tolerance_max" => __('maintenance.tolerance_inverted', ['position' => $position]),
                ]);
            }

            if ($type === 'CHOICE' && blank($item['options_json'] ?? null)) {
                throw ValidationException::withMessages([
                    "items.{$index}.options_json" => __('maintenance.choice_needs_options', ['position' => $position]),
                ]);
            }
        }
    }
}
