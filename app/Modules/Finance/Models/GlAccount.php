<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of the chart of accounts (Master Data spec §12).
 *
 * Tenant-owned: no two companies share a chart. Self-referencing so "Stock"
 * can sit under "Current Assets"; the GL mapping (Shared Systems §2) points
 * at leaf rows by id, which is what lets finance rename or renumber later
 * without touching a posting.
 */
class GlAccount extends BaseModel
{
    use BelongsToTenant;

    public const CATEGORIES = ['ASSET', 'LIABILITY', 'INCOME', 'EXPENSE', 'EQUITY'];

    protected $table = 'gl_accounts';

    protected $fillable = ['company_id', 'code', 'name', 'account_category', 'parent_account_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_account_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_account_id');
    }
}
