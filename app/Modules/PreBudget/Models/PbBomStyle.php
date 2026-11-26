<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One of a budget's styles in a BOM (a style is in one BOM at a time), with its own accessory lines. */
class PbBomStyle extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pb_bom_styles';

    protected $fillable = ['company_id', 'bom_id', 'budget_id', 'style', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PbBomLine::class, 'bom_style_id')->orderBy('sort_order');
    }
}
