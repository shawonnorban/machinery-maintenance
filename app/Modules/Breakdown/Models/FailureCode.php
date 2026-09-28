<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The vocabulary a garment maintenance team already uses (Seed Catalog 3).
 *
 * UNKNOWN is seeded deliberately. Without it a technician under pressure picks
 * a wrong code, and wrong data is worse than absent data; the share of UNKNOWN
 * closures is then reportable as a data-quality figure rather than hidden
 * inside plausible-looking codes.
 */
class FailureCode extends BaseModel
{
    protected $table = 'failure_codes';

    protected $fillable = [
        'company_id', 'failure_category_id', 'name', 'name_bn', 'code', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FailureCategory::class, 'failure_category_id');
    }

    public function scopeAvailableTo(Builder $query, string $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }

    public function label(): string
    {
        return app()->getLocale() === 'bn' && filled($this->name_bn)
            ? $this->name_bn
            : $this->name;
    }
}
