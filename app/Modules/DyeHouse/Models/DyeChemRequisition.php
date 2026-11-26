<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Dyes and chemicals asked of a chemical sub-store (IR-DC-YYYYMM####) for a batch programme, a machine wash, a finishing process or a re-process, and what it is for; a re-process waits on the approval desk before the store may issue. Dye House → Requisition (AnnotechERP 17/05).
 */
class DyeChemRequisition extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['BATCH_PROGRAM', 'MC_WASH', 'FINISHING', 'REPROCESS'];

    public const PROCESS_TYPES = ['RE_MATCHING', 'RE_WASH', 'RE_DYEING', 'STRIPPING', 'SOFTENER'];

    public const APPROVALS = ['NOT_REQUIRED', 'PENDING', 'APPROVED', 'REJECTED'];

    public const STATUSES = ['WAITING', 'PARTIAL', 'ISSUED', 'CANCELLED'];

    protected $table = 'dye_chem_requisitions';

    protected $fillable = ['company_id', 'req_no', 'req_date', 'req_for', 'req_type', 'process_type', 'party_id', 'store_id', 'batch_id', 'dye_machine_id', 'colour_id', 'buyer_id', 'order_id', 'remarks', 'approval', 'decided_by', 'decided_at', 'status', 'issued_by', 'issued_at', 'created_by'];

    protected function casts(): array
    {
        return ['req_date' => 'date', 'decided_at' => 'datetime', 'issued_at' => 'datetime'];
    }
}
