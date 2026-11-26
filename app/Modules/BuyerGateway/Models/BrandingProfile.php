<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The logo, theme and wording one buyer sees in the portal. Buyer Gateway → Portal Branding Setup (AnnotechERP 25/05).
 */
class BrandingProfile extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_branding_profiles';

    protected $fillable = ['company_id', 'buyer_id', 'theme', 'accent_slot', 'logo_file', 'welcome_line', 'support_email', 'updated_on', 'live'];

    protected function casts(): array
    {
        return ['accent_slot' => 'integer', 'updated_on' => 'date', 'live' => 'boolean'];
    }
}
