<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A notice published to the portal for an audience and a window. Buyer Gateway → Portal Announcements (AnnotechERP 25/04).
 */
class Announcement extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_announcements';

    protected $fillable = ['company_id', 'title', 'audience', 'buyer_id', 'kind', 'published_on', 'expires_on', 'reads', 'draft', 'body'];

    protected function casts(): array
    {
        return ['published_on' => 'date', 'expires_on' => 'date', 'reads' => 'integer', 'draft' => 'boolean'];
    }
}
