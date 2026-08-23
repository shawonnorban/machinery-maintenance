<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Models;

use App\Modules\WorkOrder\Models\WorkOrderChecklistResult;
use App\Shared\Casts\MoneyCast;
use App\Shared\Models\BaseModel;
use App\Shared\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of a checklist. Immutable once its version is published
 * (Data Dictionary 2.5, SRS 12).
 */
class ChecklistItem extends BaseModel
{
    public const INPUT_TYPES = ['PASS_FAIL', 'NUMERIC', 'TEXT', 'CHOICE', 'PHOTO', 'SIGNATURE'];

    protected $fillable = [
        'company_id', 'template_version_id', 'sequence', 'label', 'help_text',
        'input_type', 'unit', 'options_json', 'expected_value',
        'tolerance_min', 'tolerance_max', 'required', 'allows_attachment',
        'requires_attachment_on_fail', 'requires_note_on_fail',
        'fail_creates_followup_work_order', 'is_safety_item',
    ];

    protected function casts(): array
    {
        return [
            'options_json' => 'array',
            'sequence' => 'integer',
            'required' => 'boolean',
            'allows_attachment' => 'boolean',
            'requires_attachment_on_fail' => 'boolean',
            'requires_note_on_fail' => 'boolean',
            'fail_creates_followup_work_order' => 'boolean',
            'is_safety_item' => 'boolean',
            'expected_value' => MoneyCast::class,
            'tolerance_min' => MoneyCast::class,
            'tolerance_max' => MoneyCast::class,
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTemplateVersion::class, 'template_version_id');
    }

    /**
     * What technicians have recorded against this line.
     *
     * Without the tenant scope, because the only question asked of it is
     * whether anybody at all has filled this item in — which is what decides
     * whether a seeded item may still be removed.
     */
    public function results(): HasMany
    {
        return $this->hasMany(WorkOrderChecklistResult::class, 'checklist_item_id')
            ->withoutGlobalScope(TenantScope::class);
    }

    public function isNumeric(): bool
    {
        return $this->input_type === 'NUMERIC';
    }

    public function hasTolerance(): bool
    {
        return $this->tolerance_min !== null || $this->tolerance_max !== null;
    }

    /**
     * A numeric reading outside tolerance is a fail, whatever the technician
     * tapped (Data Dictionary, checklist rules 3).
     */
    public function isWithinTolerance(string|float|null $value): ?bool
    {
        if ($value === null || ! $this->hasTolerance()) {
            return null;
        }

        $numeric = (float) $value;

        if ($this->tolerance_min !== null && $numeric < (float) $this->tolerance_min) {
            return false;
        }

        if ($this->tolerance_max !== null && $numeric > (float) $this->tolerance_max) {
            return false;
        }

        return true;
    }
}
