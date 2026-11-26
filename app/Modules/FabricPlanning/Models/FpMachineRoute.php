<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The dye machine a fabric is routed to, by fabric group, fiber template, buyer and GSM band (Dye Machine Route Master). Fabric Requirement → Master Data (AnnotechERP 13/01).
 */
class FpMachineRoute extends BaseModel
{
    use BelongsToTenant;

    public const FABRIC_GROUPS = ['BODY', 'COLLAR', 'LINING', 'RIB'];

    protected $table = 'fp_machine_routes';

    protected $fillable = ['company_id', 'colour_group_id', 'fabric_group', 'fiber_template', 'buyer_id', 'dye_machine_id', 'gsm_from', 'gsm_to', 'active'];

    protected function casts(): array
    {
        return ['gsm_from' => 'integer', 'gsm_to' => 'integer', 'active' => 'boolean'];
    }
}
