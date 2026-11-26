<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A dyeing process loss for a fabric type, composition, colour group and finished-weight band (Dyeing Process Loss Norms). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class DyeingLossBand extends BaseModel
{
    use BelongsToTenant;

    public const COLOR_GROUPS = ['WHITE', 'LIGHT', 'MEDIUM', 'DARK', 'EXTRA_DARK', 'MELANGE'];

    protected $table = 'dyeing_loss_bands';

    protected $fillable = ['company_id', 'fabric_type_id', 'fiber_composition_id', 'color_group', 'weight_low', 'weight_high', 'loss_percent', 'active'];

    protected function casts(): array
    {
        return ['weight_low' => 'decimal:3', 'weight_high' => 'decimal:3', 'loss_percent' => 'decimal:2', 'active' => 'boolean'];
    }
}
