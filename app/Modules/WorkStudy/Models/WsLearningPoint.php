<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One day of a learning curve: the efficiency expected on that day of a style run (Learning Curve Model). Work Study → Master Data (AnnotechERP 14/01).
 */
class WsLearningPoint extends BaseModel
{
    use BelongsToTenant;

    public const FABRIC_CLASSES = ['FLEECE', 'PIQUE', 'SINGLE_JERSEY', 'TWILL'];

    /** Product criticality bands by SMV: basic 0–12, semi critical 12.01–20, critical 20.01–35, highly critical 35.01–99. */
    public const PROD_TYPES = ['BASIC', 'SEMI_CRITICAL', 'CRITICAL', 'HIGHLY_CRITICAL'];

    protected $table = 'ws_learning_points';

    protected $fillable = ['company_id', 'fabric_class', 'prod_type', 'day', 'efficiency'];

    protected function casts(): array
    {
        return ['day' => 'integer', 'efficiency' => 'decimal:2'];
    }
}
