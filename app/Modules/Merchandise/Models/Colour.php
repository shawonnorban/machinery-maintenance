<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A colour a garment or fabric comes in, with its Pantone reference (Colour Directory). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class Colour extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['SOLID', 'MELANGE', 'YARN_DYED', 'AOP', 'STRIPE'];

    protected $table = 'colours';

    protected $fillable = ['company_id', 'code', 'colour_type', 'name', 'pantone_no', 'version_no', 'colour_group_id', 'swatch_provided', 'active'];

    protected function casts(): array
    {
        return ['swatch_provided' => 'boolean', 'active' => 'boolean'];
    }
}
