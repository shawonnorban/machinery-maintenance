<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A nature of work a garment item may be given, several at a time — Cutting, Printing, Sewing … (Master Data, AnnotechERP 06/01). */
class WorkNature extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'work_natures';

    protected $fillable = ['company_id', 'code', 'name', 'remarks', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer'];
    }
}
