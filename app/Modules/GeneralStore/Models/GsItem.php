<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Models;

use App\Modules\GeneralStore\Support\GsBook;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A general-store item — category, unit, brand, minimum stock, price and rack (Item Master). General Store → Master Data (AnnotechERP 10/01).
 */
class GsItem extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'gs_items';

    protected $fillable = ['company_id', 'gs_category_id', 'gs_unit_id', 'item_code', 'name', 'sub_category', 'brand', 'minimum_stock', 'reorder_level', 'default_price', 'rack_location', 'specification', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['minimum_stock' => 'decimal:4', 'reorder_level' => 'decimal:4', 'default_price' => 'decimal:4', 'active' => 'boolean'];
    }

    /** An item added or edited goes on the store's audit trail, as Brickland keeps it ("Item ITM-065 — Laptop"). */
    protected static function booted(): void
    {
        static::created(fn (self $i) => GsBook::audit('CREATE', 'gs-item', $i->item_code, "Item {$i->item_code} — {$i->name}"));
        static::updated(fn (self $i) => GsBook::audit('UPDATE', 'gs-item', $i->item_code, "Item {$i->item_code} — {$i->name}"));
    }
}
