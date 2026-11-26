<?php

declare(strict_types=1);

namespace App\Modules\Approval\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One named person a step lands on (Approval Workflow Designer → Action
 * Assignment, AnnotechERP 02/03). A step with people named is theirs alone.
 */
class ApprovalRuleUser extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'approval_rule_users';

    protected $fillable = ['company_id', 'rule_id', 'user_id'];
}
