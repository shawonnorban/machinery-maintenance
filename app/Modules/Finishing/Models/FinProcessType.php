<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A finishing process a batch may be booked against, its rate and the loss it may book unexplained (Finishing Process Types). Finishing Line → Master Data (AnnotechERP 18/01).
 */
class FinProcessType extends BaseModel
{
    use BelongsToTenant;

    /** Where the floor status board shows a batch — a process's WIP stage, a mapping's batch and next status. */
    public const STAGES = ['AWAITING_LOAD', 'DYEING_RUNNING', 'DEWATERING', 'DRYING', 'HEAT_SET', 'STENTERING', 'COMPACTING', 'CALENDERING', 'BRUSHING', 'QC_INSPECTION', 'READY_FOR_DELIVERY', 'DELIVERED', 'ON_HOLD', 'AT_SUB_CONTRACT'];

    protected $table = 'fin_process_types';

    protected $fillable = ['company_id', 'code', 'name', 'short_name', 'group_id', 'subgroup_id', 'link_process_id', 'wip_stage', 'rate', 'loss_pct', 'display_order', 'active'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'loss_pct' => 'decimal:2', 'display_order' => 'integer', 'active' => 'boolean'];
    }
}
