<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A T&A milestone — order confirmation, lab dip, cutting start … (Task & Action Register). Merchandise Desk → Master Data (AnnotechERP 06/01).
 * Access Control → TNA Task Status Setup (02/04) keeps its event setup: message, parent, section, owner, starting status, repeat and buyer feedback.
 */
class TnaTask extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tna_tasks';

    /** Where a raised instance of an event can stand (TNA Task Status Setup, AnnotechERP 02/04). */
    public const STATUSES = ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'DELAYED', 'CANCELLED'];

    protected $fillable = [
        'company_id', 'tna_task_group_id', 'code', 'sort_order', 'name', 'name_bn', 'source_module', 'offset_days', 'is_sample', 'is_start', 'is_end', 'is_production', 'active',
        'task_message', 'parent_id', 'section_id', 'assign_to', 'execution_by', 'default_status', 'is_repeat', 'is_buyer_feedback',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'offset_days' => 'integer', 'is_sample' => 'boolean', 'is_start' => 'boolean', 'is_end' => 'boolean', 'is_production' => 'boolean', 'active' => 'boolean', 'is_repeat' => 'boolean', 'is_buyer_feedback' => 'boolean'];
    }
}
