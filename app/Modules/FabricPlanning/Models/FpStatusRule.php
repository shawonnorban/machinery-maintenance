<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A score band of a constraint family: what it is called and the colour the board paints it. Fabric Requirement → Planning Constraint Ruleset (AnnotechERP 13/02).
 */
class FpStatusRule extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fp_status_rules';

    protected $fillable = ['company_id', 'group_id', 'score_from', 'score_to', 'status_code', 'status_desc', 'color_code', 'green_rank', 'sort_order'];

    protected function casts(): array
    {
        return ['score_from' => 'float', 'score_to' => 'float', 'green_rank' => 'float', 'sort_order' => 'integer'];
    }

    /** The board's reading of a band, from its green rank: Poor, Watch or Good. */
    public static function standing(float $rank): string
    {
        return match (true) {
            $rank < 0.5 => 'POOR',
            $rank < 0.75 => 'WATCH',
            default => 'GOOD',
        };
    }
}
