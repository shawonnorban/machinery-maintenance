<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The yarn counts a fabric type takes for a GSM band (GSM-Wise Yarn Count Setup). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class YarnCountConfig extends BaseModel
{
    use BelongsToTenant;

    public const TEMPLATES = ['COTTON_100', 'LYCRA_BLEND', 'CVC_BLEND'];

    protected $table = 'yarn_count_configs';

    protected $fillable = ['company_id', 'fabric_type_id', 'template', 'gauge', 'gsm_min', 'gsm_max', 'count_top', 'count_binding', 'count_back'];

    protected function casts(): array
    {
        return ['gsm_min' => 'integer', 'gsm_max' => 'integer'];
    }
}
