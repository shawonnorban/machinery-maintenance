<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Resources;

use App\Modules\GatePass\Models\GatePassItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Item / Product: what an item gate pass carries out — finished garments,
 * fabric, trims, samples, machinery, documents … — and its unit, from Gate
 * Pass's own list. Gate Pass → Master Data (AnnotechERP 11/01).
 */
class GatePassItemResource extends GatePassMasterResource
{
    public function key(): string
    {
        return 'gatepass-items';
    }

    public function model(): string
    {
        return GatePassItem::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'item_category', 'unit', 'description', 'active'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return $this->basicRules($row) + [
            'item_category' => [$required, Rule::in(GatePassItem::CATEGORIES)],
            'unit' => [$required, Rule::in(GatePassItem::UNITS)],
        ];
    }
}
