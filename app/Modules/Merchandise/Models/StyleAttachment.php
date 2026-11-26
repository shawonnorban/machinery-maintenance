<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A file attached to a development style — a view, the tech pack, measurement sheet, swatch, artwork or other (AnnotechERP 06/07). */
class StyleAttachment extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['STYLE_IMAGE', 'FRONT_VIEW', 'BACK_VIEW', 'TECH_PACK', 'MEASUREMENT_SHEET', 'FABRIC_SWATCH', 'ARTWORK', 'OTHER'];

    protected $table = 'merch_style_attachments';

    protected $fillable = ['company_id', 'style_id', 'kind', 'file_id', 'file_name'];

    protected function casts(): array
    {
        return [];
    }
}
