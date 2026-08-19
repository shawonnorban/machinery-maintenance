<?php

declare(strict_types=1);

namespace App\Modules\Costing\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cost categories (SRS 23).
 *
 * Each rolls up into a lifecycle bucket so a machine's total cost of ownership
 * can be assembled without anyone deciding, per report, whether a vendor
 * invoice counts as maintenance.
 */
class CostCategory extends BaseModel
{
    public const LIFECYCLE_BUCKETS = [
        'ACQUISITION', 'INSTALLATION', 'UPGRADE', 'MAINTENANCE', 'REPAIR', 'OTHER',
    ];

    protected $table = 'cost_categories';

    protected $fillable = ['company_id', 'name', 'name_bn', 'code', 'lifecycle_bucket', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function scopeAvailableTo(Builder $query, string $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }

    public function label(): string
    {
        return app()->getLocale() === 'bn' && filled($this->name_bn) ? $this->name_bn : $this->name;
    }
}
