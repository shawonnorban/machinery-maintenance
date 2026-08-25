<?php

declare(strict_types=1);

namespace App\Modules\Asset\Models;

use App\Modules\Tenancy\Models\Factory;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The core domain entity. A machine is an asset subtype, not a separate table
 * (ADR-006), so generators, boilers and compressors need no schema change.
 */
class Asset extends BaseModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (self $asset): void {
            $asset->updateQuietly([
                'deleted_marker' => 'DELETED_'.$asset->getKey(),
            ]);
        });

        static::restoring(function (self $asset): void {
            $asset->updateQuietly(['deleted_marker' => 'LIVE']);
        });
    }

    /** Data Dictionary 2.4. */
    public const STATUSES = [
        'DRAFT', 'PURCHASED', 'INSTALLED', 'COMMISSIONED', 'RUNNING', 'IDLE',
        'UNDER_MAINTENANCE', 'BREAKDOWN', 'UNDER_REPAIR', 'RETIRED', 'SCRAPPED', 'LOST',
    ];

    /** Statuses a client may set directly at creation. */
    public const CREATABLE_STATUSES = ['DRAFT', 'PURCHASED', 'INSTALLED'];

    public const CRITICALITIES = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];

    /**
     * Allowed status transitions (Data Dictionary 3.3).
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['PURCHASED'],
        'PURCHASED' => ['INSTALLED'],
        'INSTALLED' => ['COMMISSIONED'],
        'COMMISSIONED' => ['RUNNING', 'IDLE'],
        'RUNNING' => ['IDLE', 'UNDER_MAINTENANCE', 'BREAKDOWN', 'RETIRED', 'LOST'],
        'IDLE' => ['RUNNING', 'UNDER_MAINTENANCE', 'BREAKDOWN', 'RETIRED', 'LOST'],
        'UNDER_MAINTENANCE' => ['RUNNING', 'IDLE', 'BREAKDOWN'],
        'BREAKDOWN' => ['UNDER_REPAIR'],
        'UNDER_REPAIR' => ['RUNNING', 'IDLE', 'RETIRED', 'SCRAPPED'],
        'RETIRED' => ['SCRAPPED', 'RUNNING'],
        'SCRAPPED' => [],
        'LOST' => ['RUNNING', 'SCRAPPED'],
    ];

    /**
     * Transitions only an elevated user may perform: recommissioning something
     * retired, or resurrecting a lost asset.
     *
     * @var list<string>
     */
    public const ELEVATED_TRANSITIONS = ['RETIRED>RUNNING', 'LOST>RUNNING'];

    protected $fillable = [
        'company_id', 'asset_type_id', 'asset_category_id', 'manufacturer_id',
        'asset_model_id', 'parent_asset_id', 'asset_code', 'serial_number',
        'barcode', 'name', 'description', 'criticality', 'status',
        'country_of_origin', 'purchase_date', 'installation_date',
        'commissioning_date', 'acquisition_cost', 'installation_cost',
        'current_value', 'capitalized_cost', 'salvage_value',
        'useful_life_months', 'depreciation_method', 'expected_life_cycles',
        'currency', 'warranty_start', 'warranty_end', 'supplier_id',
        'default_meter_type_id', 'current_factory_id', 'asset_location_id',
        'is_imported', 'imported_batch_id', 'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'installation_date' => 'date',
            'commissioning_date' => 'date',
            'warranty_start' => 'date',
            'warranty_end' => 'date',
            'retired_at' => 'datetime',
            'scrapped_at' => 'datetime',
            'is_imported' => 'boolean',
            'version' => 'integer',
            'useful_life_months' => 'integer',
            'acquisition_cost' => MoneyCast::class,
            'installation_cost' => MoneyCast::class,
            'current_value' => MoneyCast::class,
            'capitalized_cost' => MoneyCast::class,
            'salvage_value' => MoneyCast::class,
            'disposal_value' => MoneyCast::class,
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class, 'asset_model_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_asset_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_asset_id');
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'current_factory_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'asset_location_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AssetStatusHistory::class)->orderByDesc('changed_at');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(AssetTransfer::class)->orderByDesc('transfer_at');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function transitionRequiresElevation(string $status): bool
    {
        return in_array("{$this->status}>{$status}", self::ELEVATED_TRANSITIONS, true);
    }

    /**
     * An asset that can no longer be worked on. Breakdowns, work orders and
     * transfers all refuse to touch one.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, ['SCRAPPED', 'RETIRED', 'LOST'], true);
    }

    public function warrantyIsActive(): bool
    {
        return $this->warranty_end !== null && $this->warranty_end->isFuture();
    }
}
