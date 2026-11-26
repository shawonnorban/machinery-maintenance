<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One column of a specification sheet. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class TrimSpecControl extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['TEXT', 'NUMBER', 'SELECT', 'YESNO', 'DATE'];

    protected $table = 'trim_spec_controls';

    protected $fillable = ['company_id', 'trim_spec_template_id', 'name', 'control_type', 'options', 'required', 'sort_order'];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'sort_order' => 'integer'];
    }
}
