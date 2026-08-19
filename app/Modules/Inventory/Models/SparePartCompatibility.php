<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetModel;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which machines a part fits, and what may stand in for it (SRS 20).
 *
 * The substitute link is the one that earns its keep at 2am: the specified
 * part is out of stock, and the question is whether anything on the shelf will
 * do.
 */
class SparePartCompatibility extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['FITS', 'SUBSTITUTE'];

    protected $table = 'spare_part_compatibilities';

    protected $fillable = [
        'company_id', 'spare_part_id', 'asset_model_id', 'asset_id',
        'compatibility_type', 'substitute_for_part_id',
    ];

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function assetModel(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function substituteFor(): BelongsTo
    {
        return $this->belongsTo(SparePart::class, 'substitute_for_part_id');
    }
}
