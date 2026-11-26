<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One checkpoint of a sample quality check. */
class SampleQualityCheckpoint extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'proto_quality_checkpoints';

    protected $fillable = ['company_id', 'quality_check_id', 'checkpoint', 'result', 'defect', 'severity', 'remarks', 'sort_order'];
}
