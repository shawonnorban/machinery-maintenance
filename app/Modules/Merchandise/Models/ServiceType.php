<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A kind of outside process — AOP, yarn dyeing, print, embroidery, wash (Service Type Setup). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class ServiceType extends BaseModel
{
    use BelongsToTenant;

    public const SERVICES = ['AOP', 'YARN_DYEING', 'PRINT', 'EMBROIDERY', 'GMT_WASH'];

    protected $table = 'service_types';

    protected $fillable = ['company_id', 'service_for', 'code', 'name', 'remarks', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'active' => 'boolean'];
    }
}
