<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A shade on a lab dip programme: colour, Pantone, fabric, light source and target date, and its submission to the buyer (AnnotechERP 06/10). */
class LabdipShade extends BaseModel
{
    use BelongsToTenant;

    /** The light sources a lab dip is judged under. */
    public const LIGHT_SOURCES = ['D65', 'TL84', 'CWF', 'U30', 'U35', 'A', 'UV'];

    /** Where a shade stands with the buyer (Lab Dip Submission). */
    public const STATUSES = ['PENDING', 'SUBMITTED', 'APPROVED', 'REJECTED', 'RESUBMIT'];

    /** The buyer's verdicts: each is dated the day it is recorded. */
    public const VERDICTS = ['APPROVED', 'REJECTED', 'RESUBMIT'];

    protected $table = 'merch_labdip_shades';

    protected $fillable = ['company_id', 'program_id', 'style_id', 'order_id', 'colour', 'pantone', 'fabric', 'light_source', 'target_date', 'sort_order', 'status', 'submitted_on', 'decided_on', 'feedback'];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'submitted_on' => 'date', 'decided_on' => 'date'];
    }
}
