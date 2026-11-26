<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A line of a fabric booking: a dia-wise consumption paired with the garment
 * item, colour and size range it applies to, the fabric colour, and its
 * process figures. Its pieces and finished fabric quantity are derived.
 */
class FabricBookingLine extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['KNIT', 'WOVEN', 'TRIMS'];

    public const DIA_UNITS = ['INCH', 'CM'];

    public const DIA_TYPES = ['OPEN', 'TUBE'];

    public const CONS_UNITS = ['KG', 'YDS', 'MTR'];

    public const CONS_PER = ['DZ', 'PCS'];

    protected $table = 'merch_fabric_booking_lines';

    protected $fillable = [
        'company_id', 'booking_id', 'fabric_kind', 'fabric_type_id', 'garment_part_id', 'finish_dia', 'dia_unit', 'dia_type', 'consumption', 'cons_unit', 'cons_per',
        'all_orders', 'gmt_style_id', 'all_colours', 'gmt_colour_id', 'all_sizes', 'size_group', 'fab_same_as_gmt', 'fab_colour_id', 'colour_spec', 'contrast',
        'cutting_wastage', 'add_qty', 'disp_order', 'pieces', 'finish_qty', 'uom', 'gsm', 'rate', 'process_loss',
    ];

    protected function casts(): array
    {
        return [
            'finish_dia' => 'float', 'consumption' => 'float', 'all_orders' => 'boolean', 'all_colours' => 'boolean', 'all_sizes' => 'boolean', 'fab_same_as_gmt' => 'boolean',
            'contrast' => 'boolean', 'cutting_wastage' => 'float', 'add_qty' => 'float', 'disp_order' => 'integer', 'pieces' => 'float', 'finish_qty' => 'float',
            'gsm' => 'float', 'rate' => 'float', 'process_loss' => 'float',
        ];
    }

    /**
     * A booking's finished fabric per unit — kilos and yards are never added
     * (2026-11-16): "105 kg + 300 yds", and the single figure only when there
     * is one unit.
     *
     * @param  \Illuminate\Support\Collection<int, self|object>  $lines  with finish_qty and cons_unit (or uom)
     * @return array{qty: ?float, qty_label: string}
     */
    public static function unitTotals(\Illuminate\Support\Collection $lines): array
    {
        $by = $lines->groupBy(fn ($l) => strtoupper((string) ($l->cons_unit ?? $l->uom ?? 'KG')))->map(fn ($g) => round((float) $g->sum('finish_qty'), 3))->filter(fn ($v) => $v > 0);
        $label = $by->map(fn ($v, $u) => rtrim(rtrim(number_format($v, 3, '.', ','), '0'), '.').' '.strtolower($u))->implode(' + ');

        return ['qty' => $by->count() === 1 ? $by->first() : ($by->isEmpty() ? 0.0 : null), 'qty_label' => $label !== '' ? $label : '0'];
    }
}
