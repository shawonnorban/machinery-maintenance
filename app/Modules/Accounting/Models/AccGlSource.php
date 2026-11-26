<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A GL source (Access Control → Ledger Integration Setup, AnnotechERP 02/03):
 * where an amount comes from — named by its Link Ref — and the Chart of
 * Account ledgers its COA lines debit and credit.
 */
class AccGlSource extends BaseModel
{
    use BelongsToTenant;

    public const MAPPING_TYPES = ['GENERAL', 'BANK_BILL_COLLECTION'];

    protected $table = 'acc_gl_sources';

    protected $fillable = ['company_id', 'name', 'name_bn', 'mapping_type', 'link_ref', 'final_bill_collection', 'editable_from_gl', 'active'];

    protected function casts(): array
    {
        return ['final_bill_collection' => 'boolean', 'editable_from_gl' => 'boolean', 'active' => 'boolean'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccGlSourceLine::class, 'gl_source_id')->orderBy('sort_order');
    }
}
