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

    public const ENTITY_TYPES = ['WORK_ORDER', 'INVENTORY_TRANSFER', 'COST_ENTRY'];

    protected $table = 'approval_workflows';

    protected $fillable = ['company_id', 'name', 'entity_type', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ApprovalRule::class, 'workflow_id')->orderBy('sequence');
    }
}
