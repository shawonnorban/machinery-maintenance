<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A development style (Style Development Sheet, AnnotechERP 06/05): the buyer
 * and account it is for, the confirmed inquiry it came from, its reference
 * (main ref and extension), what kind of item it is (set, model, combo,
 * colour pair), its unit, season, origin and status. Orders are placed on it.
 */
class Style extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'DEVELOPMENT', 'STYLE_APPROVED', 'RUNNING', 'CLOSED', 'ON_HOLD', 'CANCELLED'];

    /** What the registration form offers; Running and Closed are set on the sheet as orders run. */
    public const FORM_STATUSES = ['DRAFT', 'DEVELOPMENT', 'STYLE_APPROVED', 'ON_HOLD', 'CANCELLED'];

    public const QTY_UNITS = ['PCS', 'SET', 'DZN'];

    protected $table = 'merch_styles';

    protected $fillable = ['company_id', 'buyer_id', 'buyer_office_id', 'inquiry_id', 'style_ref', 'style_ext', 'description', 'is_set', 'is_model', 'is_combo', 'is_colour_pair', 'prev_style_id', 'qty_unit', 'pcs_per_unit', 'season', 'ref_no', 'nature_of_work', 'supplier_id', 'origin_country_id', 'status', 'created_by', 'dev_no', 'approval_status', 'sent_at', 'sent_by'];

    protected function casts(): array
    {
        return ['is_set' => 'boolean', 'is_model' => 'boolean', 'is_combo' => 'boolean', 'is_colour_pair' => 'boolean', 'pcs_per_unit' => 'integer', 'dev_no' => 'integer', 'sent_at' => 'datetime'];
    }

    /** "MOOD SHIRT-327632.01": the main reference and its extension. */
    public function styleNo(): string
    {
        return $this->style_ext ? "{$this->style_ref}-{$this->style_ext}" : (string) $this->style_ref;
    }
}
