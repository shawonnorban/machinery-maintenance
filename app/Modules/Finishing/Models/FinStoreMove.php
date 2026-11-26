<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One move of a store lot — received, issued to cutting, returned or transferred away — signed kilos and rolls, against the document that made it. Finishing Line → Fabric Store (AnnotechERP 18/05).
 */
class FinStoreMove extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['RECEIPT', 'ISSUE', 'RETURN', 'TRANSFER'];

    protected $table = 'fin_store_moves';

    protected $fillable = ['company_id', 'lot_id', 'kind', 'qty_kg', 'qty_m', 'rolls', 'move_date', 'doc_type', 'doc_id', 'doc_no', 'created_by'];

    protected function casts(): array
    {
        return ['qty_kg' => 'decimal:3', 'rolls' => 'integer', 'move_date' => 'date'];
    }
}
