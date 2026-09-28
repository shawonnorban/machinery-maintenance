<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Models;

use App\Modules\Asset\Models\AssetType;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceTemplate extends BaseModel
{
    protected $fillable = [
        'company_id', 'asset_type_id', 'maintenance_type_id',
        'name', 'code', 'description', 'status', 'created_by',
    ];

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MaintenanceTemplateVersion::class, 'template_id')
            ->orderByDesc('version_number');
    }

    /**
     * The version a new plan should attach to. A plan must never bind to a
     * draft: the checklist could still change underneath it.
     */
    public function currentVersion(): ?MaintenanceTemplateVersion
    {
        return $this->versions()
            ->where('status', 'PUBLISHED')
            ->orderByDesc('version_number')
            ->first();
    }

    public function draftVersion(): ?MaintenanceTemplateVersion
    {
        return $this->versions()->where('status', 'DRAFT')->first();
    }

    public function scopeAvailableTo(Builder $query, string $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }

    /** Platform-seeded templates are cloned, never edited (Seed Catalog 1). */
    public function isEditable(): bool
    {
        return $this->company_id !== null;
    }
}
