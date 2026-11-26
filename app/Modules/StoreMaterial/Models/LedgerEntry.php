<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use RuntimeException;

/**
 * One movement of a stock bucket — a GRN, Material Out, Material Return,
 * Adjustment or Reversal — that the Stock Ledger runs its balance over.
 * Append-only: a mistake is put right by a reversal, never by editing the
 * books. Store & Material (AnnotechERP 09).
 */
class LedgerEntry extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['GRN', 'MATERIAL_OUT', 'MATERIAL_RETURN', 'ADJUSTMENT', 'REVERSAL'];

    public $timestamps = false;

    /** To the microsecond, so a document's entries keep their order. */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $table = 'sm_ledger';

    protected $fillable = ['company_id', 'stock_id', 'entry_date', 'type', 'reference', 'source_id', 'in_qty', 'out_qty', 'return_qty', 'created_by', 'created_at'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'in_qty' => 'decimal:3', 'out_qty' => 'decimal:3', 'return_qty' => 'decimal:3', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Stock ledger entries cannot be edited; post a reversal.'));
        static::deleting(fn () => throw new RuntimeException('Stock ledger entries cannot be deleted; post a reversal.'));
    }
}
