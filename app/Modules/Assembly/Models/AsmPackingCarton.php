<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A carton of a packing batch with its size break-up, weights, marking and seal. Assembly Floor → Packing Process (AnnotechERP 20/05).
 */
class AsmPackingCarton extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['OPEN', 'MARKED', 'SEALED'];

    protected $table = 'asm_packing_cartons';

    protected $fillable = ['company_id', 'batch_id', 'serial', 'carton_no', 'carton_type', 'carton_size', 'gross_weight', 'net_weight', 'qty', 'status', 'sealed_at', 'sealed_by', 'unseal_reason', 'created_by'];

    protected function casts(): array
    {
        return ['serial' => 'integer', 'gross_weight' => 'decimal:2', 'net_weight' => 'decimal:2', 'qty' => 'integer', 'sealed_at' => 'datetime'];
    }

    /** The carton's barcode: its number without the dashes. */
    public function barcode(): string
    {
        return str_replace('-', '', $this->carton_no);
    }
}
