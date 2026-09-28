<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\WorkOrder\Models\WorkOrder;

class MaintenanceTypeData extends MasterDataType
{
    public function key(): string
    {
        return 'maintenance-types';
    }

    public function model(): string
    {
        return MaintenanceType::class;
    }

    public function group(): string
    {
        return 'maintenance';
    }

    public function fields(): array
    {
        return [
            Field::text('name'),
            Field::code(),
            Field::enum('default_priority', Breakdown::PRIORITIES),
            // Planned work is scheduled and counts towards PM compliance;
            // unplanned work is what the schedule was meant to prevent.
            Field::boolean('is_planned'),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [
            WorkOrder::class => 'maintenance_type_id',
            MaintenancePlan::class => 'maintenance_type_id',
            MaintenanceTemplate::class => 'maintenance_type_id',
        ];
    }
}
