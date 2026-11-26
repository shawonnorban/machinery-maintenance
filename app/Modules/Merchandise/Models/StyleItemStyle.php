<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A style an item serves ("For Style(s)#"). */
class StyleItemStyle extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_style_item_styles';

    protected $fillable = ['company_id', 'style_item_id', 'style_id'];
}
