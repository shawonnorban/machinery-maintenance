<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The library's own department axis — Yarn Store, Garments Store, General
 * Store (Master Data spec §4). Where an item is kept and who is asked for
 * it, not the organisation chart: HR and Finishing are departments of the
 * company, not of the item library.
 *
 * `default_item_type` is what a new item filed here defaults to, stored on
 * the row so renaming "Yarn Store" to "Yarn Godown" cannot break it.
 */
class ItemDepartment extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'item_departments';

    protected $fillable = ['company_id', 'code', 'name', 'name_bn', 'default_item_type', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function label(): string
    {
        return app()->getLocale() === 'bn' && filled($this->name_bn) ? $this->name_bn : $this->name;
    }
}
