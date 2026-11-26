<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use RuntimeException;

/**
 * One movement of a roll — received, out, returned or reversed — that its
 * available quantity is read from. Append-only, as the stock ledger is.
 * Store & Material (AnnotechERP 09).
 */
class RollMove extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    /** To the microsecond, so a document's moves keep their order. */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $table = 'sm_roll_moves';

    protected $fillable = ['company_id', 'roll_id', 'entry_date', 'type', 'reference', 'source_id', 'in_qty', 'out_qty', 'return_qty', 'created_by', 'created_at'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'in_qty' => 'decimal:3', 'out_qty' => 'decimal:3', 'return_qty' => 'decimal:3', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Roll movements cannot be edited; post a reversal.'));
        static::deleting(fn () => throw new RuntimeException('Roll movements cannot be deleted; post a reversal.'));
    }
}
