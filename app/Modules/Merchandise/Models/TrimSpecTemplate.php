<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A specification template for a trim or accessory (Trims & Accessories Spec Setup). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class TrimSpecTemplate extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'trim_spec_templates';

    protected $fillable = ['company_id', 'code', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
