<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A purchased fabric opening balance: opening stock carried into the period, one batch per row (Trims & Accessories, AnnotechERP 06/06). */
class FabricOpeningBalance extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'POSTED', 'LOCKED'];

    public const SOURCES = ['PHYSICAL_COUNT', 'LEGACY_MIGRATION', 'STOCK_TAKE', 'OTHER'];

    public const CATEGORIES = ['PURCHASE', 'WOVEN', 'KNIT', 'DENIM', 'LINING'];

    public const GROUPS = ['BODY', 'COLLAR', 'LINING', 'RIB'];

    public const DIA_UOMS = ['INCH', 'CM'];

    public const DIA_TYPES = ['OPEN', 'TUBE'];

    public const UOMS = ['KG', 'LBS', 'YDS', 'MTR'];

    protected $table = 'merch_pfab_obs';

    protected $fillable = [
        'company_id', 'ob_no', 'unit_company_id', 'store_id', 'ob_date', 'order_id', 'currency', 'conv_rate', 'usd_bdt', 'source', 'reference_no', 'attachment',
        'attachment_file_id', 'remarks', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['ob_date' => 'date', 'conv_rate' => 'float', 'usd_bdt' => 'float'];
    }

    /** Only a draft is edited or deleted. */
    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }
}
