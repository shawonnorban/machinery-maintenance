<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Models;

use App\Modules\Metering\Models\MeterType;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePlanRule extends BaseModel
{
    use BelongsToTenant;

    public const RULE_TYPES = ['TIME', 'METER', 'USAGE', 'CONDITION'];

    public const TIME_UNITS = ['HOUR', 'DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR'];

    protected $table = 'maintenance_plan_rules';

    protected $fillable = [
        'company_id', 'maintenance_plan_id', 'rule_type',
        'operator', 'value', 'unit', 'meter_type_id',
    ];

    protected function casts(): array
    {
        return ['value' => MoneyCast::class];
    }

    public function meterType(): BelongsTo
    {
        return $this->belongsTo(MeterType::class);
    }
}
