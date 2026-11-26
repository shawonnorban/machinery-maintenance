<?php

declare(strict_types=1);

namespace App\Modules\Cutting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cutting as the cutting master lays it (Cutting Input): PO, colour,
 * cutting number, part, fabric lot and marker — plies, size ratio — and the
 * fabric it takes; then, as the output clerk records it (Cutting Output),
 * what was really cut per size, the rejects and the fabric used. Bundle cards
 * are cut from its output.
 */
class CutInput extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['INPUT', 'OUTPUT', 'CANCELLED'];

    protected $fillable = ['company_id', 'input_no', 'cut_date', 'order_id', 'po_no', 'style', 'buyer_id', 'colour', 'cutting_no', 'parts_name', 'batch_no', 'lot_id', 'garment', 'fabric_type', 'gsm', 'dia_width',
        'marker_length', 'plies', 'lay_m', 'fabric_kg', 'status', 'used_kg', 'used_m', 'remnant_kg', 'remnant_m', 'remarks', 'output_remarks', 'created_by', 'output_by', 'output_at'];

    protected function casts(): array
    {
        return ['cut_date' => 'date', 'cutting_no' => 'integer', 'plies' => 'integer', 'marker_length' => 'float', 'fabric_kg' => 'float', 'used_kg' => 'float', 'lay_m' => 'float', 'used_m' => 'float', 'remnant_m' => 'float', 'remnant_kg' => 'float', 'output_at' => 'datetime'];
    }

    /** @return HasMany<CutInputSize, $this> */
    public function sizes(): HasMany
    {
        return $this->hasMany(CutInputSize::class, 'input_id')->orderBy('sort_order');
    }
}
