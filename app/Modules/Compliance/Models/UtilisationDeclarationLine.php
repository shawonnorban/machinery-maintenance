<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Models;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item on a utilisation declaration (§3.2).
 *
 * `wastage_percent` is declared separately from the quantity rather than
 * folded into it, because customs allows a stated wastage and the
 * reconciliation has to be able to say whether consumption stayed inside
 * it. A single "declared" number cannot answer that.
 */
class UtilisationDeclarationLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'utilisation_declaration_lines';

    protected $fillable = [
        'company_id', 'utilisation_declaration_id', 'item_id', 'item_code', 'item_name',
        'uom_id', 'declared_quantity', 'wastage_percent', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'declared_quantity' => MoneyCast::class,
            'wastage_percent' => 'decimal:2',
        ];
    }

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(UtilisationDeclaration::class, 'utilisation_declaration_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /** Declared quantity plus the wastage customs allowed on top. */
    public function allowedQuantity(): string
    {
        $declared = (string) $this->declared_quantity;
        $allowance = bcdiv(bcmul($declared, (string) $this->wastage_percent, 6), '100', 4);

        return bcadd($declared, $allowance, 4);
    }
}
