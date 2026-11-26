<?php

declare(strict_types=1);

namespace App\Modules\AiCore\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** Which AI service one AI feature of the ERP uses, and whether it runs on its own. */
class AiFeature extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ai_features';

    protected $fillable = ['company_id', 'feature', 'service_id', 'auto_run', 'enabled'];

    protected function casts(): array
    {
        return ['auto_run' => 'boolean', 'enabled' => 'boolean'];
    }
}
