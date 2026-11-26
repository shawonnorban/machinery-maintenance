<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A kind of certificate the lab holds, and who issues it ("OEKO-TEX Standard 100 — OEKO-TEX"). Colour Lab → Operations (AnnotechERP 16/01).
 */
class LabCertType extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'lab_cert_types';

    protected $fillable = ['company_id', 'code', 'name', 'issuing_body', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
