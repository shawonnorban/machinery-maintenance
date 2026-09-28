<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Category → sub-category → detail category for the item library (Master
 * Data spec §2.2), in one self-referencing table.
 *
 * Company-owned: the legacy library had 600 of these and no two factories
 * organise a store the same way, so the platform seeds none. Depth is
 * enforced when a row is saved, not by the schema.
 */
class ItemCategory extends BaseModel
{
    use BelongsToTenant;

    public const MAX_DEPTH = 3;

    protected $table = 'item_categories';

    protected $fillable = ['company_id', 'parent_id', 'name', 'name_bn', 'code', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function label(): string
    {
        return app()->getLocale() === 'bn' && filled($this->name_bn) ? $this->name_bn : $this->name;
    }

    /**
     * 1 for a top-level category, 2 for a sub-category, 3 for a detail category.
     *
     * Walks up by query rather than through `parent` so it works on a row
     * that was never eager-loaded (lazy loading is off outside production).
     */
    public function depth(): int
    {
        $depth = 1;
        $parentId = $this->parent_id;

        while ($parentId !== null) {
            $depth++;
            $parentId = self::query()->whereKey($parentId)->value('parent_id');
        }

        return $depth;
    }

    /** Ids of every row above this one, nearest first. */
    public function ancestorIds(): array
    {
        $ids = [];
        $parentId = $this->parent_id;

        while ($parentId !== null) {
            $ids[] = $parentId;
            $parentId = self::query()->whereKey($parentId)->value('parent_id');
        }

        return $ids;
    }

    /** How many levels hang below this row, counting itself: 1 for a leaf. */
    public function subtreeDepth(): int
    {
        $deepest = 1;

        foreach (self::query()->where('parent_id', $this->getKey())->get() as $child) {
            $deepest = max($deepest, 1 + $child->subtreeDepth());
        }

        return $deepest;
    }
}
