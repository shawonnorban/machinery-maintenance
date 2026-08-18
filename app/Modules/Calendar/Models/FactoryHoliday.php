<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

class FactoryHoliday extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'factory_id', 'date', 'name', 'is_working_day',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_working_day' => 'boolean',
        ];
    }
}
