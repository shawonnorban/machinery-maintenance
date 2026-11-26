<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A unit stock is counted in — PCS, KG, Box, Carton … (Unit Master). General
 * Store → Master Data (AnnotechERP 10/01).
 *
 * The one unit list (the user's decision of 2026-11-15): `units_of_measure`,
 * which every module's unit pick reads. The platform's units (no company) are
 * offered to every company and cannot be changed from a company's screen;
 * a company adds its own beside them.
 */
class GsUnit extends BaseModel
{
    protected $table = 'units_of_measure';

    protected $fillable = ['company_id', 'code', 'name', 'active'];

    protected static function booted(): void
    {
        // This company's units and the platform's.
        static::addGlobalScope('available', function (Builder $query): void {
            $context = app(TenantContext::class);
            if ($context->hasContext()) {
                $query->where(fn (Builder $q) => $q->where($q->qualifyColumn('company_id'), $context->companyId())->orWhereNull($q->qualifyColumn('company_id')));
            }
        });
        static::creating(function (Model $unit): void {
            $context = app(TenantContext::class);
            if ($unit->getAttribute('company_id') === null && $context->hasContext()) {
                $unit->setAttribute('company_id', $context->companyId());
            }
        });
    }

    /** A platform unit belongs to every company and is changed only by the platform. */
    public function isPlatform(): bool
    {
        return $this->getAttribute('company_id') === null;
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
