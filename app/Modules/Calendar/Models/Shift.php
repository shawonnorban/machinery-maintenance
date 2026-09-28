<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'factory_id', 'name', 'code',
        'start_time', 'end_time', 'crosses_midnight', 'days_of_week',
        'is_overtime', 'effective_from', 'effective_to', 'status',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'crosses_midnight' => 'boolean',
            'is_overtime' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(ShiftBreak::class);
    }

    public function runsOn(int $isoWeekday): bool
    {
        return in_array($isoWeekday, $this->days_of_week, true);
    }
}
