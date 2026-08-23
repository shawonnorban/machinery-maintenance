<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A company's own format for one kind of document number (SRS 52).
 *
 * The absence of a row is meaningful: it is the platform default, which is
 * what a company starts with. Nothing seeds these, so a company that never
 * opens the numbering screen has no rows at all.
 */
class NumberSequenceFormat extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = ['company_id', 'document_type', 'format', 'padding', 'updated_by'];

    protected function casts(): array
    {
        return ['padding' => 'integer'];
    }
}
