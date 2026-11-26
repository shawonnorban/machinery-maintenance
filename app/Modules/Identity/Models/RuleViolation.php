<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A business rule broken on a document (Access Control → Rule Violation Log, AnnotechERP 02/04). The rule is a BUSINESS_RULE lookup value.
 */
class RuleViolation extends BaseModel
{
    use BelongsToTenant;

    public const SEVERITIES = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];

    public const STATUSES = ['OPEN', 'ACKNOWLEDGED', 'WAIVED', 'RESOLVED'];

    protected $table = 'rule_violations';

    protected $fillable = ['company_id', 'rule_code', 'document_reference', 'severity', 'status', 'detail', 'raised_by'];
}
