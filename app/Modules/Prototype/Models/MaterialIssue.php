<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** Material released against an approved requisition (MI-2026-00001); never edited or removed once saved. */
class MaterialIssue extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'proto_material_issues';

    protected $fillable = ['company_id', 'issue_no', 'requisition_id', 'issue_date', 'store_id', 'received_by', 'remarks', 'issued_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date'];
    }
}
