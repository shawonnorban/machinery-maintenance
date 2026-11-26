<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A bundle scanned off its sewing line: the whole bundle, or a short output, read against what went on. Assembly Floor → Sewing (AnnotechERP 20/01).
 */
class AsmBundleOut extends BaseModel
{
    use BelongsToTenant;

    public const MODES = ['FULL', 'SHORT'];

    protected $table = 'asm_bundle_outs';

    protected $fillable = ['company_id', 'bundle_in_id', 'out_date', 'mode', 'output_qty', 'balance_qty', 'created_by'];

    protected function casts(): array
    {
        return ['out_date' => 'date', 'output_qty' => 'integer', 'balance_qty' => 'integer'];
    }
}
