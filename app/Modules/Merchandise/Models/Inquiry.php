<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A buyer's inquiry and the FOB quoted against it (Price Quotation Entry,
 * AnnotechERP 06/05). It starts as a draft; the quotation is sent, revised
 * (each revision bumps its number), and finally confirmed or cancelled. A
 * confirmed inquiry is what a style is registered against.
 */
class Inquiry extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'QUOTATION_SENT', 'REVISED', 'CONFIRMED', 'CANCELLED'];

    public const INCO_TERMS = ['FOB', 'CFR', 'CIF', 'CPT', 'EXW', 'DDP'];

    public const LC_TERMS = ['LC_AT_SIGHT', 'LC_30', 'LC_60', 'LC_90', 'TT_ADVANCE', 'TT_DELIVERY'];

    public const CONS_UNITS = ['KG', 'LBS', 'YDS', 'MTR'];

    public const CALC_UNITS = ['DZ', 'PCS', 'SET'];

    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD', 'BDT'];

    protected $table = 'merch_inquiries';

    protected $fillable = ['company_id', 'inquiry_no', 'buyer_id', 'inco_term', 'lc_term', 'cons_unit', 'calc_unit', 'currency', 'exchange_rate', 'style_no', 'style_ext', 'style_desc', 'inquiry_date', 'quoted_fob', 'remarks', 'revision', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['inquiry_date' => 'date', 'exchange_rate' => 'decimal:4', 'quoted_fob' => 'decimal:4', 'revision' => 'integer'];
    }
}
