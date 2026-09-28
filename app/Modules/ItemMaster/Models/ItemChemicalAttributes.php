<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Handling flags for a chemical item (Master Data spec §2.5). */
class ItemChemicalAttributes extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'item_chemical_attributes';

    protected $fillable = ['company_id', 'item_id', 'expiry_tracking', 'hazard_info', 'batch_tracking'];

    protected function casts(): array
    {
        return ['expiry_tracking' => 'boolean', 'batch_tracking' => 'boolean'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
