<?php

declare(strict_types=1);

namespace App\Modules\Asset\Models;

use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Section;
use App\Modules\Tenancy\Models\Workstation;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The single addressable location entity (ADR-052).
 *
 * A location has a stable identity that transfer history can reference for
 * the life of the record, its own code and QR label for floor-level scanning,
 * and a real foreign key that the database enforces.
 */
class AssetLocation extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'factory_id', 'building_id', 'floor_id', 'department_id',
        'section_id', 'production_line_id', 'workstation_id',
        'name', 'code', 'qr_code', 'full_path', 'status',
    ];

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    /** The machines standing here. */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'asset_location_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    /**
     * Rebuilds the denormalised display path. Called on save and when the
     * hierarchy above it changes.
     *
     * Every level, in the order somebody walking the floor would name them,
     * and the empty ones simply drop out.
     */
    public function buildFullPath(): string
    {
        $parts = array_filter([
            $this->factory?->name,
            $this->building?->name,
            $this->floor?->name,
            $this->department?->name,
            $this->section?->name,
            $this->productionLine?->name,
            $this->workstation?->name,
            $this->name,
        ]);

        return implode(' › ', $parts);
    }
}
