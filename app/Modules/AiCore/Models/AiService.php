<?php

declare(strict_types=1);

namespace App\Modules\AiCore\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An AI service the company has a key for (Claude, OpenAI, Gemini). The key is
 * encrypted at rest and hidden from every serialisation.
 */
class AiService extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ai_services';

    protected $fillable = ['company_id', 'provider', 'model', 'api_key', 'enabled', 'last_tested_at', 'last_test_ok', 'last_test_message'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'enabled' => 'boolean', 'last_tested_at' => 'datetime', 'last_test_ok' => 'boolean'];
    }
}
