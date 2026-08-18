<?php

declare(strict_types=1);

namespace App\Modules\Asset\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only. Every material status change is auditable (SRS 6), so there is
 * no updated_at and no soft delete (ERD rule 19).
 */
class AssetStatusHistory extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'asset_status_histories';

    protected $fillable = [
        'company_id', 'asset_id', 'from_status', 'to_status',
        'changed_by', 'changed_at', 'reason', 'source',
    ];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
