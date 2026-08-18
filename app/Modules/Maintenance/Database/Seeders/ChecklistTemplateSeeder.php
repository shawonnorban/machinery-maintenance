<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Database\Seeders;

use App\Modules\Asset\Models\AssetType;
use App\Modules\Maintenance\Models\ChecklistItem;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use App\Modules\Maintenance\Models\MaintenanceType;
use Illuminate\Database\Seeder;

/**
 * The five seeded checklists from Seed Catalog 9.
 *
 * Platform-seeded and published, so a factory can run its first PM the day it
 * signs up rather than authoring 14 items first. A tenant clones a template to
 * customise it.
 *
 * Item flags are not filler:
 *   safety     -> requires a note and a photo on fail, and raises a follow-up
 *   numeric    -> carries a unit so a reading of "12" is not ambiguous
 */
class ChecklistTemplateSeeder extends Seeder
{
    /**
     * Shorthand: [label, input, required, flags]
     * flags: s = safety, n = numeric unit follows in the label array
     *
     * @return array<string, array{asset_type: string, maintenance_type: string, name: string, duration: int, items: list<array<string, mixed>>}>
     */
    public static function templates(): array
    {
        return [
            'PM-SEWING-MONTHLY' => [
                'asset_type' => 'SEWING',
                'maintenance_type' => 'PREVENTIVE',
                'name' => 'Lockstitch sewing machine — monthly PM',
                'duration' => 45,
                'items' => [
                    ['Machine switched off and isolated before work', 'PASS_FAIL', true, 'safety'],
                    ['Clean hook race and bobbin area', 'PASS_FAIL', true],
                    ['Oil level in reservoir', 'PASS_FAIL', true],
                    ['Check and replace needle', 'PASS_FAIL', true],
                    ['Feed dog condition', 'PASS_FAIL', true],
                    ['Presser foot alignment and pressure', 'PASS_FAIL', true],
                    ['Thread tension test on sample fabric', 'PASS_FAIL', true],
                    ['Belt tension and condition', 'PASS_FAIL', true],
                    ['Motor noise and temperature', 'PASS_FAIL', true],
                    ['Stitch per inch measured', 'NUMERIC', true, null, 'SPI', 8, 14],
                    ['Needle guard and eye guard fitted', 'PASS_FAIL', true, 'safety'],
                    ['Earthing continuity checked', 'PASS_FAIL', true, 'safety'],
                    ['Machine cleaned and work area cleared', 'PASS_FAIL', true],
                    ['Photo of machine after service', 'PHOTO', false],
                ],
            ],
            'PM-GENERATOR-MONTHLY' => [
                'asset_type' => 'UTILITY',
                'maintenance_type' => 'PREVENTIVE',
                'name' => 'Generator — monthly PM',
                'duration' => 90,
                'items' => [
                    ['Engine oil level and condition', 'PASS_FAIL', true],
                    ['Coolant level', 'PASS_FAIL', true],
                    ['Fuel level and leak check', 'PASS_FAIL', true, 'safety'],
                    ['Battery voltage', 'NUMERIC', true, null, 'V', 12, 14],
                    ['Air filter condition', 'PASS_FAIL', true],
                    ['Belt tension', 'PASS_FAIL', true],
                    ['Exhaust leak check', 'PASS_FAIL', true, 'safety'],
                    ['No-load test run duration', 'NUMERIC', true, null, 'min', 10, 60],
                    ['Output voltage on test', 'NUMERIC', true, null, 'V', 380, 420],
                    ['Frequency on test', 'NUMERIC', true, null, 'Hz', 49, 51],
                    ['Automatic transfer switch operation', 'PASS_FAIL', true],
                    ['Running hours reading', 'NUMERIC', true, null, 'hours'],
                ],
            ],
            'INSP-BOILER-WEEKLY' => [
                'asset_type' => 'UTILITY',
                'maintenance_type' => 'INSPECTION',
                'name' => 'Boiler — weekly inspection',
                'duration' => 60,
                // Boiler inspection is a regulated activity: an unsupported
                // pass is worthless in an audit, so every item demands
                // evidence on failure (Seed Catalog 9.3).
                'items' => [
                    ['Water level indicator clear and correct', 'PASS_FAIL', true, 'safety'],
                    ['Blowdown performed', 'PASS_FAIL', true, 'safety'],
                    ['Safety valve tested', 'PASS_FAIL', true, 'safety'],
                    ['Pressure gauge reading', 'NUMERIC', true, null, 'bar'],
                    ['Feed water pump operation', 'PASS_FAIL', true, 'safety'],
                    ['Steam leak inspection', 'PASS_FAIL', true, 'safety'],
                    ['Flue gas temperature', 'NUMERIC', false, null, '°C'],
                    ['Chemical dosing level', 'PASS_FAIL', true, 'safety'],
                    ['Fire and safety clearance around unit', 'PASS_FAIL', true, 'safety'],
                    ['Operator log signed', 'SIGNATURE', true],
                ],
            ],
            'PM-COMPRESSOR-MONTHLY' => [
                'asset_type' => 'UTILITY',
                'maintenance_type' => 'PREVENTIVE',
                'name' => 'Air compressor — monthly PM',
                'duration' => 60,
                'items' => [
                    ['Oil level and condition', 'PASS_FAIL', true],
                    ['Air filter cleaned or replaced', 'PASS_FAIL', true],
                    ['Condensate drained', 'PASS_FAIL', true],
                    ['Discharge pressure', 'NUMERIC', true, null, 'bar', 6, 8],
                    ['Air leak survey on distribution line', 'PASS_FAIL', true],
                    ['Belt and coupling condition', 'PASS_FAIL', true],
                    ['Safety valve check', 'PASS_FAIL', true, 'safety'],
                    ['Running hours reading', 'NUMERIC', true, null, 'hours'],
                ],
            ],
            'PM-CUTTING-MONTHLY' => [
                'asset_type' => 'CUTTING',
                'maintenance_type' => 'PREVENTIVE',
                'name' => 'Cutting machine — monthly PM',
                'duration' => 40,
                'items' => [
                    ['Blade condition and sharpness', 'PASS_FAIL', true, 'safety'],
                    ['Blade guard fitted and functional', 'PASS_FAIL', true, 'safety'],
                    ['Sharpening stone condition', 'PASS_FAIL', true],
                    ['Base plate condition', 'PASS_FAIL', true],
                    ['Motor and gear noise', 'PASS_FAIL', true],
                    ['Lubrication points serviced', 'PASS_FAIL', true],
                    ['Power cable condition', 'PASS_FAIL', true, 'safety'],
                    ['Emergency stop functional', 'PASS_FAIL', true, 'safety'],
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::templates() as $code => $definition) {
            $assetType = AssetType::whereNull('company_id')
                ->where('code', $definition['asset_type'])
                ->first();

            $maintenanceType = MaintenanceType::whereNull('company_id')
                ->where('code', $definition['maintenance_type'])
                ->first();

            $template = MaintenanceTemplate::updateOrCreate(
                ['company_id' => null, 'code' => $code],
                [
                    'name' => $definition['name'],
                    'asset_type_id' => $assetType?->id,
                    'maintenance_type_id' => $maintenanceType?->id,
                    'status' => 'ACTIVE',
                ],
            );

            $version = MaintenanceTemplateVersion::updateOrCreate(
                ['template_id' => $template->id, 'version_number' => 1],
                [
                    'company_id' => null,
                    'status' => 'PUBLISHED',
                    'estimated_duration_minutes' => $definition['duration'],
                    'published_at' => now(),
                    'effective_from' => now()->toDateString(),
                ],
            );

            $version->items()->delete();

            foreach ($definition['items'] as $index => $item) {
                [$label, $inputType, $required] = [$item[0], $item[1], $item[2]];
                $isSafety = ($item[3] ?? null) === 'safety';

                ChecklistItem::create([
                    'company_id' => null,
                    'template_version_id' => $version->id,
                    'sequence' => $index + 1,
                    'label' => $label,
                    'input_type' => $inputType,
                    'unit' => $item[4] ?? null,
                    'tolerance_min' => $item[5] ?? null,
                    'tolerance_max' => $item[6] ?? null,
                    'required' => $required,
                    'is_safety_item' => $isSafety,
                    // A failed safety item needs evidence and a note, and it
                    // raises corrective work automatically. A tick in a box is
                    // not a record of a guard that was missing.
                    'requires_attachment_on_fail' => $isSafety,
                    'requires_note_on_fail' => $isSafety,
                    'fail_creates_followup_work_order' => $isSafety,
                ]);
            }
        }
    }
}
