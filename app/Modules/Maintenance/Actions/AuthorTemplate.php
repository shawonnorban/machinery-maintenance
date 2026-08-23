<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\Models\ChecklistItem;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writing a checklist (SRS 12).
 *
 * The seeded templates were the only ones a factory could ever use, because
 * nothing in the product could author one — so a dye house ran its soft flow
 * machines against a checklist written for a sewing floor, or against nothing.
 *
 * Versioning is the whole shape of this class. A published version is frozen:
 * editing it would silently rewrite what a technician signed to say they had
 * checked, and a compliance record that changes after the fact is worse than
 * none. So changes go into a new draft, and publishing it supersedes the old
 * one from that day forward while every completed work order keeps resolving
 * the version it actually ran.
 */
class AuthorTemplate
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTemplate(array $data, ?string $userId = null): MaintenanceTemplate
    {
        return DB::transaction(function () use ($data, $userId): MaintenanceTemplate {
            $template = MaintenanceTemplate::create([
                'company_id' => $this->context->companyId(),
                'asset_type_id' => filled($data['asset_type_id'] ?? null) ? $data['asset_type_id'] : null,
                'maintenance_type_id' => filled($data['maintenance_type_id'] ?? null) ? $data['maintenance_type_id'] : null,
                'name' => $data['name'],
                'code' => strtoupper(trim((string) $data['code'])),
                'description' => $data['description'] ?? null,
                'status' => 'ACTIVE',
                'created_by' => $userId,
            ]);

            // A template with no version is a name with nothing behind it, so
            // the first draft is born with it.
            $this->newDraft($template, $data['estimated_duration_minutes'] ?? null);

            return $template->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTemplate(MaintenanceTemplate $template, array $data): MaintenanceTemplate
    {
        $this->assertOwned($template);

        $template->update([
            'asset_type_id' => filled($data['asset_type_id'] ?? null) ? $data['asset_type_id'] : null,
            'maintenance_type_id' => filled($data['maintenance_type_id'] ?? null) ? $data['maintenance_type_id'] : null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return $template->fresh();
    }

    /**
     * Start a new draft from whatever is published now.
     *
     * The items are copied, because a revision is almost always "the same
     * fourteen checks with one changed" and retyping them is how a check goes
     * missing.
     */
    public function newDraft(MaintenanceTemplate $template, ?int $durationMinutes = null): MaintenanceTemplateVersion
    {
        $this->assertOwned($template);

        $existingDraft = MaintenanceTemplateVersion::where('template_id', $template->id)
            ->where('status', 'DRAFT')
            ->first();

        if ($existingDraft !== null) {
            throw ValidationException::withMessages([
                'version' => __('maintenance.draft_already_open'),
            ])->status(422);
        }

        $current = MaintenanceTemplateVersion::where('template_id', $template->id)
            ->where('status', 'PUBLISHED')
            ->orderByDesc('version_number')
            ->first();

        $highest = (int) MaintenanceTemplateVersion::where('template_id', $template->id)
            ->max('version_number');

        return DB::transaction(function () use ($template, $current, $highest, $durationMinutes): MaintenanceTemplateVersion {
            $draft = MaintenanceTemplateVersion::create([
                'company_id' => $this->context->companyId(),
                'template_id' => $template->id,
                'version_number' => $highest + 1,
                'status' => 'DRAFT',
                'estimated_duration_minutes' => $durationMinutes ?? $current?->estimated_duration_minutes,
                'instructions' => $current?->instructions,
                'supersedes_version_id' => $current?->id,
            ]);

            foreach ($current?->items ?? [] as $item) {
                $copy = $item->replicate(['id', 'template_version_id', 'created_at', 'updated_at']);
                $copy->template_version_id = $draft->id;
                $copy->save();
            }

            return $draft->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveItem(MaintenanceTemplateVersion $version, array $data, ?ChecklistItem $item = null): ChecklistItem
    {
        $this->assertDraft($version);

        $values = [
            'label' => $data['label'],
            'input_type' => $data['input_type'],
            'unit' => $data['unit'] ?? null,
            'tolerance_min' => filled($data['tolerance_min'] ?? null) ? $data['tolerance_min'] : null,
            'tolerance_max' => filled($data['tolerance_max'] ?? null) ? $data['tolerance_max'] : null,
            'required' => (bool) ($data['required'] ?? false),
            'help_text' => $data['help_text'] ?? null,
        ];

        // A safety item is not a stricter tick box: it needs a photo and a note
        // when it fails, and it raises corrective work by itself. A guard that
        // was missing is not a line in a list.
        $isSafety = (bool) ($data['is_safety_item'] ?? false);

        $values += [
            'is_safety_item' => $isSafety,
            'requires_attachment_on_fail' => $isSafety || (bool) ($data['requires_attachment_on_fail'] ?? false),
            'requires_note_on_fail' => $isSafety || (bool) ($data['requires_note_on_fail'] ?? false),
            'fail_creates_followup_work_order' => $isSafety || (bool) ($data['fail_creates_followup_work_order'] ?? false),
        ];

        if ($item !== null) {
            $item->update($values);

            return $item->fresh();
        }

        $next = (int) ChecklistItem::where('template_version_id', $version->id)->max('sequence') + 1;

        return ChecklistItem::create($values + [
            'company_id' => $version->company_id,
            'template_version_id' => $version->id,
            'sequence' => $next,
        ]);
    }

    public function removeItem(MaintenanceTemplateVersion $version, ChecklistItem $item): void
    {
        $this->assertDraft($version);

        DB::transaction(function () use ($version, $item): void {
            $item->delete();

            // Resequenced, so the numbers a technician reads down the page have
            // no holes in them.
            $remaining = ChecklistItem::where('template_version_id', $version->id)
                ->orderBy('sequence')
                ->get();

            foreach ($remaining as $index => $row) {
                $row->forceFill(['sequence' => $index + 1])->save();
            }
        });
    }

    public function publish(MaintenanceTemplateVersion $version, ?string $userId = null): MaintenanceTemplateVersion
    {
        $this->assertDraft($version);

        if ($version->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => __('maintenance.cannot_publish_empty'),
            ])->status(422);
        }

        return DB::transaction(function () use ($version, $userId): MaintenanceTemplateVersion {
            $today = CarbonImmutable::now();

            // The version this one replaces stops being effective the day
            // before, so no day has two published checklists and none has
            // neither.
            MaintenanceTemplateVersion::where('template_id', $version->template_id)
                ->where('status', 'PUBLISHED')
                ->get()
                ->each(fn (MaintenanceTemplateVersion $old) => $old->forceFill([
                    'status' => 'ARCHIVED',
                    'effective_to' => $today->subDay()->toDateString(),
                ])->save());

            $version->forceFill([
                'status' => 'PUBLISHED',
                'published_at' => $today,
                'published_by' => $userId,
                'effective_from' => $today->toDateString(),
            ])->save();

            return $version->fresh();
        });
    }

    private function assertDraft(MaintenanceTemplateVersion $version): void
    {
        if (! $version->isEditable()) {
            throw ValidationException::withMessages([
                'version' => __('maintenance.version_is_frozen'),
            ])->status(409);
        }
    }

    private function assertOwned(MaintenanceTemplate $template): void
    {
        if ($template->company_id === null) {
            // Platform templates are shared with every tenant. A company that
            // wants its own wording clones one.
            throw ValidationException::withMessages([
                'template' => __('maintenance.platform_template_read_only'),
            ])->status(403);
        }
    }
}
