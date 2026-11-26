<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One register row: a lot answered by the lab or released without a measurement.
 */
class KnitYarnTestResult extends BaseModel
{
    use BelongsToTenant;

    public const BASES = ['LAB', 'WAIVED'];

    public const RESULTS = ['PENDING', 'PASSED', 'FAILED', 'CONDITIONAL'];

    protected $table = 'knit_yarn_test_results';

    protected $fillable = ['company_id', 'basis', 'test_id', 'sort_order', 'test_date', 'test_by', 'composition_id', 'yarn_count_id', 'colour_group', 'brand', 'supplier_id', 'lc_no', 'lot_no', 'colour', 'result', 'note', 'yarn_lot_id', 'created_by'];

    protected function casts(): array
    {
        return ['test_date' => 'date'];
    }
}
