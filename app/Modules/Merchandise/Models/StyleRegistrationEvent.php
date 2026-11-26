<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A step in a style registration request's life: submitted, approved, returned or rejected, with its note (AnnotechERP 06/07). */
class StyleRegistrationEvent extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_style_registration_events';

    protected $fillable = ['company_id', 'registration_id', 'action', 'note', 'user_id'];

    protected function casts(): array
    {
        return [];
    }
}
