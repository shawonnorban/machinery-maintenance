<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftBreak extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'shift_id', 'name',
        'start_time', 'end_time', 'counts_as_operating_time',
    ];

    protected function casts(): array
    {
        return ['counts_as_operating_time' => 'boolean'];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
