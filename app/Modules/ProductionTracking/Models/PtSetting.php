<?php

declare(strict_types=1);

namespace App\Modules\ProductionTracking\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The module's settings: live refresh, stale-data warning, achievement thresholds, the plan approval workflow and the SMV method (30/06). Production Tracking (AnnotechERP 30).
 */
class PtSetting extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pt_settings';

    protected $fillable = ['company_id', 'refresh_seconds', 'stale_seconds', 'on_track_pct', 'below_pct', 'approval_workflow', 'smv_method', 'updated_by'];

    protected function casts(): array
    {
        return ['refresh_seconds' => 'integer', 'stale_seconds' => 'integer', 'on_track_pct' => 'integer', 'below_pct' => 'integer', 'approval_workflow' => 'boolean', 'smv_method' => 'boolean'];
    }
}
