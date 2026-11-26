<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Grey fabric called off the batch store against a batch card (Bulk Grey Fabric Indent) — or, for a finished-fabric batch, off the finished-fabric store. Its lines are the card's. Dye House → Batch (Bulk) (AnnotechERP 17/03).
 */
class DyeGreyIndent extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['BULK', 'FINISHED', 'SAMPLE'];

    protected $table = 'dye_grey_indents';

    protected $fillable = ['company_id', 'req_no', 'req_date', 'req_type', 'batch_id', 'colour_group_id', 'proj_rate', 'store_id', 'party_id', 'created_by'];

    protected function casts(): array
    {
        return ['req_date' => 'date', 'proj_rate' => 'decimal:2'];
    }
}
