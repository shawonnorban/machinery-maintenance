<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A person answerable for dyeing finishing or grey fabric quality, and the pass code they confirm with (Finishing & Quality Owner Setup). Dye House → Master Data (AnnotechERP 17/01).
 */
class DyeResponsibleEmployee extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['DF_PROCESS_OPERATOR', 'DF_QUALITY_INSPECTION', 'GFAB_PROCESS_OPERATOR', 'GFAB_QUALITY_INSPECTION'];

    protected $table = 'dye_responsible_employees';

    protected $fillable = ['company_id', 'employee_type', 'user_id', 'employee_name', 'department', 'designation', 'pass_code_hash'];
}
