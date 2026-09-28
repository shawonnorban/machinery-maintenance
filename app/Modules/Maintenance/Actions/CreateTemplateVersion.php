<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\Models\ChecklistItem;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opens a new draft revision of a template.
 *
 * "Editing" a published checklist means this: the current version is copied
 * into a fresh draft, the draft is edited, and publishing it archives the old
 * one. Nothing that a technician already worked through is ever mutated
 * (SRS 12, API 5.2).
 */
class CreateTemplateVersion
{
    public function handle(MaintenanceTemplate $template, ?string $userId = null): MaintenanceTemplateVersion
    {
        // One draft at a time. Two open drafts on the same template makes
        // "which one am I editing" unanswerable.
        $existingDraft = $template->draftVersion();

        if ($existingDraft !== null) {
            throw ValidationException::withMessages([
                'template_id' => __('maintenance.draft_already_open', [
                    'version' => $existingDraft->version_number,
                ]),
            ])->status(409);
        }

        return DB::transaction(function () use ($template, $userId): MaintenanceTemplateVersion {
            $latest = MaintenanceTemplateVersion::query()
                ->where('template_id', $template->id)
                ->orderByDesc('version_number')
                ->first();

            $draft = MaintenanceTemplateVersion::create([
                'company_id' => $template->company_id,
                'template_id' => $template->id,
                'version_number' => ($latest?->version_number ?? 0) + 1,
                'status' => 'DRAFT',
                'estimated_duration_minutes' => $latest?->estimated_duration_minutes,
                'instructions' => $latest?->instructions,
            ]);

            // Seed the draft from the version being superseded, so editing a
            // 14-item checklist does not mean retyping 14 items.
            $source = $template->currentVersion() ?? $latest;

            if ($source !== null && $source->id !== $draft->id) {
                foreach ($source->items as $item) {
                    ChecklistItem::create(array_merge(
                        $item->only([
                            'label', 'help_text', 'input_type', 'unit', 'options_json',
                            'expected_value', 'tolerance_min', 'tolerance_max',
                            'required', 'allows_attachment', 'requires_attachment_on_fail',
                            'requires_note_on_fail', 'fail_creates_followup_work_order',
                            'is_safety_item', 'sequence',
                        ]),
                        [
                            'company_id' => $template->company_id,
                            'template_version_id' => $draft->id,
                        ],
                    ));
                }
            }

            unset($userId);

            return $draft->fresh(['items']);
        });
    }
}
