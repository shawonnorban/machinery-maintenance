<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Database\Seeders;

use App\Modules\Maintenance\Models\MaintenanceType;
use Illuminate\Database\Seeder;

/**
 * Maintenance types: Seed Catalog 6.
 *
 * `is_planned` is not decoration. It decides whether the downtime a work order
 * causes counts against availability, so a factory is not penalised for doing
 * preventive maintenance (ADR-049).
 */
class MaintenanceTypeSeeder extends Seeder
{
    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function types(): array
    {
        return [
            'PREVENTIVE' => ['Preventive maintenance', 'MEDIUM', true],
            'CORRECTIVE' => ['Corrective maintenance', 'HIGH', false],
            'BREAKDOWN' => ['Breakdown repair', 'HIGH', false],
            'EMERGENCY' => ['Emergency repair', 'CRITICAL', false],
            'INSPECTION' => ['Inspection', 'LOW', true],
            'CALIBRATION' => ['Calibration', 'MEDIUM', true],
            'CLEANING' => ['Cleaning and lubrication', 'LOW', true],
            'OVERHAUL' => ['Overhaul', 'MEDIUM', true],
            'INSTALLATION' => ['Installation and commissioning', 'MEDIUM', true],
            'CONDITION_BASED' => ['Condition-based maintenance', 'MEDIUM', true],
        ];
    }

    public function run(): void
    {
        foreach (self::types() as $code => [$name, $priority, $isPlanned]) {
            MaintenanceType::updateOrCreate(
                ['company_id' => null, 'code' => $code],
                ['name' => $name, 'default_priority' => $priority, 'is_planned' => $isPlanned],
            );
        }
    }
}
