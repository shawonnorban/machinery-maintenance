<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One dosed agent of a dosing template, in the order the bath takes it: its
 * step, phase and process, the dye or chemical, whether the lab dip doses it
 * too and whether the bath line breaks after it. Dye House → Lab-Dip (AnnotechERP 17/02).
 */
class DyeDosingTemplateAgent extends BaseModel
{
    use BelongsToTenant;

    /** phase => its processes, in Brickland's order. */
    public const PROCESSES = [
        'PRE_TREATMENT' => ['DEMINERALISATION', 'SCOURING', 'BLEACHING', 'HOT_WASH', 'NEUTRALISE'],
        'DYEING' => ['LEVELLING', 'SALT_DOSING', 'COLOUR_DOSING', 'ALKALI_DOSING', 'FIXATION'],
        'AFTER_TREATMENT' => ['ACID_NEUTRALISE', 'SOAPING', 'HOT_WASH', 'COLD_WASH'],
        'FINISHING' => ['SOFTENING', 'FIXING', 'ANTI_CREASING'],
    ];

    protected $table = 'dye_dosing_template_agents';

    protected $fillable = ['company_id', 'template_id', 'step_no', 'dye_method_id', 'phase', 'process', 'item_id', 'is_labdip', 'is_line_break', 'dose', 'uom'];

    protected function casts(): array
    {
        return ['step_no' => 'integer', 'is_labdip' => 'boolean', 'is_line_break' => 'boolean', 'dose' => 'decimal:4'];
    }
}
