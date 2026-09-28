<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Models;

use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactoryCalendar extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'factory_id', 'operating_mode',
        'weekly_off_days', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'weekly_off_days' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function isContinuous(): bool
    {
        return $this->operating_mode === 'CONTINUOUS';
    }
}
