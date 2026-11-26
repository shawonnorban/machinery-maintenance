<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A point of measure of a chart for one size: value, tolerance and unit (AnnotechERP 06/10). */
class MeasurePoint extends BaseModel
{
    /** The units a measurement is written in. */
    public const UNITS = ['CM', 'INCH'];

    use BelongsToTenant;

    protected $table = 'merch_measure_points';

    protected $fillable = ['company_id', 'chart_id', 'size', 'point', 'value', 'tolerance', 'uom', 'sort_order'];

    protected function casts(): array
    {
        return ['value' => 'float', 'tolerance' => 'float'];
    }
}
