<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A reusable T&A calendar for an order type, garment type and lead time (TNA Template Setup). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class TnaTemplate extends BaseModel
{
    use BelongsToTenant;

    public const SCOPES = ['GENERAL', 'BUYER'];

    public const ORDER_TYPES = ['NEW', 'REPEAT', 'DEVELOPMENT', 'PROJECTION'];

    public const GMT_TYPES = ['BASIC', 'SEMI_CRITICAL', 'CRITICAL'];

    protected $table = 'tna_templates';

    protected $fillable = ['company_id', 'code', 'scope', 'buyer_id', 'order_type', 'gmt_type', 'lead_from', 'lead_to', 'std_days', 'before_swing', 'after_swing', 'description', 'approved_by', 'prepared_by'];

    protected function casts(): array
    {
        return ['lead_from' => 'integer', 'lead_to' => 'integer', 'std_days' => 'integer', 'before_swing' => 'integer', 'after_swing' => 'integer'];
    }
}
