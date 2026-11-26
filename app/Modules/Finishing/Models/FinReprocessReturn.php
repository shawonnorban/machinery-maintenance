<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A batch sent back to the Dye House for shade, GSM or width correction, the reason recorded against it; it leaves the floor until received again. Finishing Line → DF Production (AnnotechERP 18/02).
 */
class FinReprocessReturn extends BaseModel
{
    use BelongsToTenant;

    public const REASONS = ['SHADE', 'GSM', 'WIDTH', 'OTHER'];

    protected $table = 'fin_reprocess_returns';

    protected $fillable = ['company_id', 'return_no', 'return_date', 'receipt_id', 'reason', 'qty_kg', 'rolls', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['return_date' => 'date', 'qty_kg' => 'decimal:3', 'rolls' => 'integer'];
    }
}
