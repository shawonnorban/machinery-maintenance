<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An in-house fabric test: the lot tested, its dia and GSM against standard, shrinkage, spirality, stitch length and fastness, and the decision on it. Colour Lab → Operations (AnnotechERP 16/01).
 */
class LabFabricTest extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'];

    public const DRYING = ['TUMBLE', 'LINE', 'FLAT', 'DRIP'];

    public const SUMMARIES = ['PASS', 'FAIL', 'CONDITIONAL'];

    /** Grey-scale grades a fastness reading takes. */
    public const GRADES = ['1', '1-2', '2', '2-3', '3', '3-4', '4', '4-5', '5'];

    protected $table = 'lab_fabric_tests';

    protected $fillable = ['company_id', 'test_no', 'test_date', 'lab_floor_id', 'buyer_id', 'order_id', 'style_no', 'fabric_type_id', 'composition_id', 'colour', 'lot_no', 'qty', 'drying', 'summary', 'std_dia', 'act_dia', 'std_gsm', 'act_gsm', 'coil_no', 'shrink_l', 'shrink_w', 'spirality', 'stitch_length', 'wash', 'rub_dry', 'rub_wet', 'light', 'perspiration', 'rh', 'remarks', 'status', 'decision_note', 'decided_at', 'decided_by', 'created_by'];

    protected function casts(): array
    {
        return ['test_date' => 'date', 'decided_at' => 'datetime', 'qty' => 'float', 'std_dia' => 'float', 'act_dia' => 'float', 'std_gsm' => 'float', 'act_gsm' => 'float', 'shrink_l' => 'float', 'shrink_w' => 'float', 'spirality' => 'float', 'stitch_length' => 'float', 'rh' => 'float'];
    }
}
