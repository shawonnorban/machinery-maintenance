<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The technical spec of a fabric item (Master Data spec §2.3). */
class ItemFabricAttributes extends BaseModel
{
    use BelongsToTenant;

    public const FABRIC_TYPES = ['WOVEN', 'KNIT', 'FLEECE', 'RIB'];

    /** What the fabric form must have before a roll can be inspected against it. */
    public const REQUIRED = ['gsm', 'width', 'composition'];

    protected $table = 'item_fabric_attributes';

    protected $fillable = [
        'company_id', 'item_id', 'fabric_type', 'composition', 'gsm', 'width',
        'construction', 'finish', 'shrinkage', 'pantone', 'color_fastness',
    ];

    protected function casts(): array
    {
        return ['gsm' => 'decimal:2', 'width' => 'decimal:2'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
