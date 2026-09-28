<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The technical spec of a yarn item (Master Data spec §2.4). */
class ItemYarnAttributes extends BaseModel
{
    use BelongsToTenant;

    public const REQUIRED = ['count', 'fiber'];

    protected $table = 'item_yarn_attributes';

    protected $fillable = ['company_id', 'item_id', 'count', 'type', 'fiber', 'twist', 'color'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
