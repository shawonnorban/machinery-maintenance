<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A step in a development style's approval: sent, approved, returned for revision or rejected, with its note (AnnotechERP 06/07). */
class StyleApproval extends BaseModel
{
    use BelongsToTenant;

    public const ACTIONS = ['SENT', 'APPROVED', 'REVISION', 'REJECTED'];

    protected $table = 'merch_style_approvals';

    protected $fillable = ['company_id', 'style_id', 'action', 'note', 'user_id'];

    protected function casts(): array
    {
        return [];
    }
}
