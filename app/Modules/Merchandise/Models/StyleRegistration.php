<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A style registration request — the first registration or a change request (Style Registration Entry, AnnotechERP 06/07). */
class StyleRegistration extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'PENDING', 'RETURNED', 'APPROVED', 'REJECTED'];

    public const GENDERS = ['MEN', 'WOMEN', 'UNISEX', 'BOYS', 'GIRLS', 'KIDS', 'INFANT'];

    /** The profile fields the registration adds; collection and division may stay blank. */
    public const PROFILE = ['brand', 'product', 'product_category', 'product_type', 'gender', 'season_year', 'collection', 'department', 'division'];

    public const REQUIRED = ['brand', 'product', 'product_category', 'product_type', 'gender', 'season_year', 'department'];

    protected $table = 'merch_style_registrations';

    protected $fillable = ['company_id', 'request_no', 'type', 'version', 'style_id', 'style_code', 'registration_no', 'status', 'active', 'brand', 'product', 'product_category', 'product_type', 'gender', 'season_year', 'collection', 'department', 'division', 'created_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'active' => 'boolean', 'season_year' => 'integer'];
    }
}
