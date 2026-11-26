<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A batch's poly label for one size, generated when Barcode & Label starts, printed and verified by scan. Assembly Floor → Packing Process (AnnotechERP 20/05).
 */
class AsmPackingLabel extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'asm_packing_labels';

    protected $fillable = ['company_id', 'batch_id', 'size', 'qty', 'barcode', 'print_count', 'printed_at', 'verified_at', 'verified_by'];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'print_count' => 'integer', 'printed_at' => 'datetime', 'verified_at' => 'datetime'];
    }
}
