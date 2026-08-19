<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Why a machine was stopped, and whether that stoppage counts (SRS 17.1).
 *
 * The class is what makes availability mean anything: a machine idle over a
 * holiday and a machine dead mid-shift are both "not running", and averaging
 * them together produces a number nobody in the factory recognises.
 */
class DowntimeReasonCode extends BaseModel
{
    public const CLASSES = ['UNPLANNED', 'PLANNED', 'NON_OPERATING', 'EXTERNAL'];

    protected $table = 'downtime_reason_codes';

    protected $fillable = [
        'company_id', 'code', 'name', 'name_bn',
        'downtime_class', 'counts_against_availability', 'active',
    ];

    protected function casts(): array
    {
        return [
            'counts_against_availability' => 'boolean',
            'active' => 'boolean',
        ];
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
