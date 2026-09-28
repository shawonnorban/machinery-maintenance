<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Freezes a draft so plans and work orders may bind to it (API 5.2).
 *
 * Publishing is the point of no return: from here the version is a historical
 * record, not a document. The previous published version is archived rather
 * than deleted, because work orders still reference it.
 */
class PublishTemplateVersion
{
    public function handle(MaintenanceTemplateVersion $version, ?string $userId = null): MaintenanceTemplateVersion
    {
        if ($version->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'status' => __('maintenance.only_drafts_publish', ['status' => $version->status]),
            ])->status(409);
        }

        $itemCount = $version->items()->count();

        if ($itemCount === 0) {
            throw ValidationException::withMessages([
                'items' => __('maintenance.cannot_publish_empty'),
            ]);
        }

        if ($version->requiredItemCount() === 0) {
            // Completion is gated on required items. A checklist with none can
            // be closed without a single answer, which makes it decorative.
            throw ValidationException::withMessages([
                'items' => __('maintenance.needs_one_required_item'),
            ]);
        }

        return DB::transaction(function () use ($version, $userId): MaintenanceTemplateVersion {
            $previous = MaintenanceTemplateVersion::query()
                ->where('template_id', $version->template_id)
                ->where('status', 'PUBLISHED')
                ->orderByDesc('version_number')
                ->first();

            if ($previous !== null) {
                // Archived, never deleted: closed work orders still resolve it.
                $previous->forceFill([
                    'status' => 'ARCHIVED',
                    'effective_to' => now()->toDateString(),
                ])->save();

                $version->supersedes_version_id = $previous->id;
            }

            $version->forceFill([
                'status' => 'PUBLISHED',
                'published_by' => $userId,
                'published_at' => now(),
                'effective_from' => $version->effective_from ?? now()->toDateString(),
                'supersedes_version_id' => $version->supersedes_version_id,
            ])->save();

            return $version;
        });
    }
}
