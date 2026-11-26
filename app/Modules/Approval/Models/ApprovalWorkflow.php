<?php

declare(strict_types=1);

namespace App\Modules\Approval\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named approval chain for one kind of record (SRS 14).
 */
class ApprovalWorkflow extends BaseModel
{
    use BelongsToTenant;

    /**
     * Wired to the engine (see ApprovalEntities): WORK_ORDER,
     * PURCHASE_REQUISITION, PURCHASE_ORDER, PRODUCTION_ISSUE and
     * PURCHASE_RETURN. INVENTORY_TRANSFER and COST_ENTRY are accepted so a
     * chain can be configured ahead of the flow that will use it.
     */
    public const ENTITY_TYPES = [
        'WORK_ORDER', 'PURCHASE_REQUISITION', 'PURCHASE_ORDER',
        'INVENTORY_TRANSFER', 'COST_ENTRY', 'PRODUCTION_ISSUE', 'PURCHASE_RETURN',
        'STOCK_COUNT', 'FABRIC_BOOKING', 'IMPORT_FILE', 'PRE_BUDGET', 'EXPORT_STATEMENT_EDIT',
    ];

    protected $table = 'approval_workflows';

    protected $fillable = ['company_id', 'name', 'entity_type', 'active', 'type_code', 'short_name', 'document_type', 'workflow_type', 'page_url'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ApprovalRule::class, 'workflow_id')->orderBy('sequence');
    }
}
