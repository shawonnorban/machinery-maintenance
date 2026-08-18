<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One frozen revision of a checklist (SRS 12).
 *
 * A work order references a VERSION, never a template. That is what lets a
 * work order closed two years ago still reproduce the exact list its
 * technician worked through, even though the template has changed since.
 */
class MaintenanceTemplateVersion extends BaseModel
{
    public const STATUSES = ['DRAFT', 'PUBLISHED', 'ARCHIVED'];

    protected $table = 'maintenance_template_versions';

    protected $fillable = [
        'company_id', 'template_id', 'version_number', 'status',
        'effective_from', 'effective_to', 'estimated_duration_minutes',
        'instructions', 'published_by', 'published_at', 'first_used_at',
        'supersedes_version_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'published_at' => 'datetime',
            'first_used_at' => 'datetime',
            'version_number' => 'integer',
            'estimated_duration_minutes' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTemplate::class, 'template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class, 'template_version_id')->orderBy('sequence');
    }

    /**
     * Only a draft may be edited. Editing a published version would silently
     * rewrite what a technician certified they had checked.
     */
    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }

    /**
     * A version that has been executed cannot be archived either. The work
     * order that used it still has to resolve it.
     */
    public function hasBeenUsed(): bool
    {
        return $this->first_used_at !== null;
    }

    public function isPublished(): bool
    {
        return $this->status === 'PUBLISHED';
    }

    /**
     * Called when a work order snapshots this version. Idempotent: only the
     * first use is recorded, because that is the point after which the
     * version can never change.
     */
    public function markUsed(): void
    {
        if ($this->first_used_at === null) {
            $this->forceFill(['first_used_at' => now()])->save();
        }
    }

    public function requiredItemCount(): int
    {
        return $this->items()->where('required', true)->count();
    }
}
