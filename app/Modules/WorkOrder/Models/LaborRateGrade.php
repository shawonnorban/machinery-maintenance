<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Models;

use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * A standard hourly rate per skill grade (ADR-065).
 *
 * Not a salary. Two technicians on the same grade cost the same, deliberately:
 * maintenance needs comparable cost per machine, not payroll accuracy, and
 * storing real pay here would make a maintenance tool an HR data store.
 */
class LaborRateGrade extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'labor_rate_grades';

    protected $fillable = [
        'company_id', 'factory_id', 'name', 'code', 'standard_hourly_rate',
        'overtime_multiplier', 'currency', 'effective_from', 'effective_to', 'active',
    ];

    protected function casts(): array
    {
        return [
            'standard_hourly_rate' => MoneyCast::class,
            'overtime_multiplier' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'active' => 'boolean',
        ];
    }

    /**
     * The rate in force on a given date. A rate change never rewrites the cost
     * of work already recorded, so the lookup is by date, not by "latest".
     */
    public function scopeEffectiveOn(Builder $query, CarbonInterface $date): Builder
    {
        return $query
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date->toDateString());
            });
    }

    public function rateFor(string $category): string
    {
        if ($category === 'OVERTIME') {
            return number_format(
                (float) $this->standard_hourly_rate * (float) $this->overtime_multiplier,
                4, '.', '',
            );
        }

        return $this->standard_hourly_rate;
    }
}
