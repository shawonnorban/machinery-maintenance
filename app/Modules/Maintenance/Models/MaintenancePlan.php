<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Models;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetType;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * When maintenance is due. A schedule is one concrete occurrence of a plan.
 */
class MaintenancePlan extends BaseModel
{
    use BelongsToTenant;

    public const TRIGGER_TYPES = ['TIME', 'METER', 'USAGE', 'CONDITION', 'COMBINED'];

    public const SCHEDULE_MODES = ['ROLLING', 'FIXED'];

    protected $fillable = [
        'company_id', 'asset_id', 'asset_type_id', 'maintenance_type_id',
        'template_version_id', 'name', 'trigger_type', 'schedule_mode',
        'rule_logic', 'priority', 'grace_period_minutes', 'lead_time_days',
        'non_working_day_policy', 'requires_shutdown', 'assigned_team_id',
        'default_technician_id', 'estimated_duration_minutes',
        'start_date', 'end_date', 'active', 'timezone',
        'last_generated_at', 'last_completed_at', 'next_due_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'active' => 'boolean',
            'requires_shutdown' => 'boolean',
            'grace_period_minutes' => 'integer',
            'lead_time_days' => 'integer',
            'estimated_duration_minutes' => 'integer',
            'last_generated_at' => 'datetime',
            'last_completed_at' => 'datetime',
            'next_due_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTemplateVersion::class, 'template_version_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(MaintenancePlanRule::class, 'maintenance_plan_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class, 'maintenance_plan_id');
    }

    public function timeRule(): ?MaintenancePlanRule
    {
        return $this->rules->firstWhere('rule_type', 'TIME');
    }

    public function meterRule(): ?MaintenancePlanRule
    {
        return $this->rules->firstWhere('rule_type', 'METER');
    }

    public function isRolling(): bool
    {
        return $this->schedule_mode === 'ROLLING';
    }

    /** "Whichever occurs first" (ADR-012). */
    public function isWhicheverFirst(): bool
    {
        return $this->trigger_type === 'COMBINED' && $this->rule_logic === 'OR';
    }

    public function hasEnded(): bool
    {
        return $this->end_date !== null && $this->end_date->isPast();
    }

    /**
     * Assets this plan covers. A plan either names one asset or applies to
     * every asset of a type, which is how a factory covers 400 sewing
     * machines with a single plan.
     *
     * @return Collection<int, Asset>
     */
    public function targetAssets()
    {
        if ($this->asset_id !== null) {
            return Asset::whereKey($this->asset_id)->get();
        }

        return Asset::where('asset_type_id', $this->asset_type_id)
            // A scrapped or retired machine does not need its next service.
            ->whereNotIn('status', ['SCRAPPED', 'RETIRED', 'LOST', 'DRAFT'])
            ->get();
    }
}
