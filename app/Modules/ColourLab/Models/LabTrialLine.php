<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One dye or chemical on a trial: its supplier, the dose and cost claimed and found, and the result. Colour Lab → Operations (AnnotechERP 16/01).
 */
class LabTrialLine extends BaseModel
{
    use BelongsToTenant;

    public const UNITS = ['%', 'g/l', 'ml/l', 'g'];

    public const RESULTS = ['PENDING', 'PASS', 'FAIL', 'RETRIAL'];

    protected $table = 'lab_trial_lines';

    protected $fillable = ['company_id', 'trial_id', 'sort_order', 'item_department_id', 'item_id', 'supplier_id', 'brand', 'origin_country_id', 'price', 'claim_dose', 'dose_unit', 'claim_cost', 'claim_ratio', 'cert_type_id', 'actual_dose', 'actual_cost', 'actual_ratio', 'result', 'comments', 'sample_qty', 'required_by'];

    protected function casts(): array
    {
        return ['price' => 'float', 'claim_dose' => 'float', 'claim_cost' => 'float', 'actual_dose' => 'float', 'actual_cost' => 'float', 'sample_qty' => 'float', 'required_by' => 'date', 'sort_order' => 'integer'];
    }
}
