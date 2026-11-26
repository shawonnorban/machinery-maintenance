<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The standard hours a dye batch takes, by fiber template, buyer category and dyeing method (Standard Batch Hour Norms). Fabric Requirement → Master Data (AnnotechERP 13/01).
 */
class FpBatchHour extends BaseModel
{
    use BelongsToTenant;

    public const TEMPLATES = ['COTTON_100', 'LYCRA_BLEND', 'CVC_BLEND'];

    public const BUYER_CATEGORIES = ['A', 'B', 'C'];

    public const METHODS = ['EXHAUST', 'COLD_PAD_BATCH', 'CONTINUOUS', 'PIGMENT'];

    protected $table = 'fp_batch_hours';

    protected $fillable = ['company_id', 'colour_group_id', 'fiber_template', 'buyer_category', 'dyeing_method', 'batch_hours'];

    protected function casts(): array
    {
        return ['batch_hours' => 'decimal:2'];
    }
}
