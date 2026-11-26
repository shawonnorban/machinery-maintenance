<?php

declare(strict_types=1);

namespace App\Modules\Cutting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A bundle card cut on the cutting floor: PO, style, colour, size, the
 * serial run it carries, the part and the fabric batch; printed, reprinted or
 * cancelled. Cutting Floor (AnnotechERP 19/01–19/03).
 */
class CutBundle extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['CREATED', 'PRINTED', 'REPRINTED', 'CANCELLED'];

    public const CUTTING_STATUSES = ['REGULAR', 'ADDITIONAL', 'PARTIAL_ADDITIONAL'];

    /** Brickland's parts, as its Parts Name picker offers them; any other may be typed. */
    public const PARTS = ['Front Panel', 'Back Panel', 'Sleeve', 'Collar', 'Cuff', 'Pocket', 'Hood', 'Waistband', 'Leg Panel', 'Placket'];

    protected $table = 'cut_bundles';

    protected $fillable = ['company_id', 'bundle_no', 'cut_date', 'order_id', 'input_id', 'po_no', 'style', 'buyer_id', 'colour', 'garment', 'fabric_type', 'gsm', 'dia_width', 'cutting_no', 'size', 'serial_from', 'serial_to', 'qty',
        'parts_name', 'batch_no', 'cutting_status', 'additional_qty', 'status', 'print_count', 'printed_at', 'created_by'];

    protected function casts(): array
    {
        return ['cut_date' => 'date', 'cutting_no' => 'integer', 'serial_from' => 'integer', 'serial_to' => 'integer', 'qty' => 'integer', 'additional_qty' => 'integer', 'print_count' => 'integer', 'printed_at' => 'datetime'];
    }

    /** The barcode a card carries: its number, hyphens dropped. */
    public function barcode(): string
    {
        return str_replace('-', '', $this->bundle_no);
    }
}
