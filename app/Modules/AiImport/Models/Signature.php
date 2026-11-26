<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The marks that make a document recognisable. AI File & PO Import → Document Recognition Engine (AnnotechERP 26/01).
 */
class Signature extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ai_signatures';

    protected $fillable = ['company_id', 'name', 'family', 'buyer_id', 'marker', 'hit_rate', 'times_matched', 'last_matched', 'enabled'];

    protected function casts(): array
    {
        return ['hit_rate' => 'float', 'times_matched' => 'integer', 'last_matched' => 'date', 'enabled' => 'boolean'];
    }
}
