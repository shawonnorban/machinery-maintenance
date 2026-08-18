<?php

declare(strict_types=1);

namespace App\Modules\Asset\Database\Seeders;

use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Asset\Models\Manufacturer;
use Illuminate\Database\Seeder;

/**
 * The garment industry asset taxonomy (Seed Catalog 2).
 *
 * Platform-seeded with a null company_id, so every tenant starts with a usable
 * taxonomy instead of inventing sixty categories before registering their
 * first machine. A tenant adds its own rows alongside these.
 */
class AssetTaxonomySeeder extends Seeder
{
    /**
     * type code => [name, default criticality, [category code => name]]
     *
     * @return array<string, array{0: string, 1: string, 2: array<string, string>}>
     */
    public static function taxonomy(): array
    {
        return [
            'SEWING' => ['Sewing Machine', 'MEDIUM', [
                'LOCKSTITCH' => 'Lockstitch',
                'OVERLOCK' => 'Overlock',
                'FLATLOCK' => 'Flatlock',
                'CHAINSTITCH' => 'Chainstitch',
                'BARTACK' => 'Bartack',
                'BUTTONHOLE' => 'Buttonhole',
                'BUTTON_ATTACH' => 'Button Attach',
                'FEED_OF_ARM' => 'Feed of the Arm',
                'ZIGZAG' => 'Zigzag',
                'BLIND_STITCH' => 'Blind Stitch',
                'KANSAI' => 'Kansai',
                'SNAP_BUTTON' => 'Snap Button',
            ]],
            'CUTTING' => ['Cutting & Spreading', 'HIGH', [
                'STRAIGHT_KNIFE' => 'Straight Knife',
                'BAND_KNIFE' => 'Band Knife',
                'ROUND_KNIFE' => 'Round Knife',
                'AUTO_CUTTER' => 'Automatic Cutter',
                'CAD_PLOTTER' => 'CAD Plotter',
                'SPREADER' => 'Spreader',
                'END_CUTTER' => 'End Cutter',
                'FUSING_MACHINE' => 'Fusing Machine',
            ]],
            'FINISHING' => ['Finishing', 'MEDIUM', [
                'STEAM_IRON' => 'Steam Iron',
                'VACUUM_TABLE' => 'Vacuum Table',
                'STEAM_PRESS' => 'Steam Press',
                'FORM_FINISHER' => 'Form Finisher',
                'THREAD_SUCKER' => 'Thread Sucker',
                'NEEDLE_DETECTOR' => 'Needle Detector',
                'METAL_DETECTOR' => 'Metal Detector',
                'TAGGING_MACHINE' => 'Tagging Machine',
            ]],
            'WET_PROCESS' => ['Washing & Dyeing', 'HIGH', [
                'WASHING_MACHINE' => 'Washing Machine',
                'HYDRO_EXTRACTOR' => 'Hydro Extractor',
                'TUMBLE_DRYER' => 'Tumble Dryer',
                'DYEING_MACHINE' => 'Dyeing Machine',
                'SAMPLE_DYEING' => 'Sample Dyeing',
                'CURING_OVEN' => 'Curing Oven',
                'SANDBLAST_CABIN' => 'Sandblast Cabin',
                'OZONE_MACHINE' => 'Ozone Machine',
            ]],
            'EMBROIDERY' => ['Embroidery', 'MEDIUM', [
                'MULTI_HEAD_EMBROIDERY' => 'Multi-head Embroidery',
                'SINGLE_HEAD_EMBROIDERY' => 'Single-head Embroidery',
                'SEQUIN_DEVICE' => 'Sequin Device',
            ]],
            'PRINTING' => ['Printing', 'MEDIUM', [
                'SCREEN_PRINT_TABLE' => 'Screen Print Table',
                'AUTO_SCREEN_PRINTER' => 'Automatic Screen Printer',
                'HEAT_TRANSFER_PRESS' => 'Heat Transfer Press',
                'DTG_PRINTER' => 'DTG Printer',
                'FLASH_CURE' => 'Flash Cure',
                'CONVEYOR_DRYER' => 'Conveyor Dryer',
            ]],
            // When a boiler or generator stops, every line stops. These are
            // seeded CRITICAL rather than left to a default.
            'UTILITY' => ['Utility & Power', 'CRITICAL', [
                'BOILER' => 'Boiler',
                'GENERATOR' => 'Generator',
                'AIR_COMPRESSOR' => 'Air Compressor',
                'AIR_DRYER' => 'Air Dryer',
                'CHILLER' => 'Chiller',
                'COOLING_TOWER' => 'Cooling Tower',
                'WATER_PUMP' => 'Water Pump',
                'ETP_UNIT' => 'ETP Unit',
                'SUBSTATION' => 'Substation',
                'TRANSFORMER' => 'Transformer',
                'UPS' => 'UPS',
                'STABILIZER' => 'Stabilizer',
            ]],
            'HVAC' => ['HVAC', 'HIGH', [
                'AHU' => 'Air Handling Unit',
                'EXHAUST_FAN' => 'Exhaust Fan',
                'HUMIDIFIER' => 'Humidifier',
                'SPLIT_AC' => 'Split AC',
                'AIR_CURTAIN' => 'Air Curtain',
            ]],
            'MATERIAL_HANDLING' => ['Material Handling', 'MEDIUM', [
                'TROLLEY' => 'Trolley',
                'HANGER_SYSTEM' => 'Hanger System',
                'CONVEYOR' => 'Conveyor',
                'FORKLIFT' => 'Forklift',
                'HOIST' => 'Hoist',
            ]],
            // Safety equipment fails silently: the failure is discovered during
            // an inspection or an emergency, never during production.
            'SAFETY' => ['Safety & Fire', 'CRITICAL', [
                'FIRE_PUMP' => 'Fire Pump',
                'FIRE_EXTINGUISHER' => 'Fire Extinguisher',
                'SMOKE_DETECTOR' => 'Smoke Detector',
                'EMERGENCY_LIGHT' => 'Emergency Light',
                'FIRE_HYDRANT' => 'Fire Hydrant',
                'SPRINKLER' => 'Sprinkler',
            ]],
            'QUALITY_LAB' => ['Quality Lab', 'MEDIUM', [
                'GSM_CUTTER' => 'GSM Cutter',
                'CROCKMETER' => 'Crockmeter',
                'TENSILE_TESTER' => 'Tensile Tester',
                'LIGHT_BOX' => 'Light Box',
                'WEIGHING_SCALE' => 'Weighing Scale',
            ]],
        ];
    }

    /**
     * Manufacturers a Bangladeshi garment factory actually has on the floor.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function manufacturers(): array
    {
        return [
            'JUKI' => ['Juki', 'JP'],
            'BROTHER' => ['Brother', 'JP'],
            'PEGASUS' => ['Pegasus', 'JP'],
            'YAMATO' => ['Yamato', 'JP'],
            'KANSAI' => ['Kansai Special', 'JP'],
            'SIRUBA' => ['Siruba', 'TW'],
            'JACK' => ['Jack', 'CN'],
            'ZOJE' => ['Zoje', 'CN'],
            'TYPICAL' => ['Typical', 'CN'],
            'GEMSY' => ['Gemsy', 'CN'],
            'DURKOPP' => ['Durkopp Adler', 'DE'],
            'VEIT' => ['Veit', 'DE'],
            'HASHIMA' => ['Hashima', 'JP'],
            'TAJIMA' => ['Tajima', 'JP'],
            'BARUDAN' => ['Barudan', 'JP'],
            'GERBER' => ['Gerber', 'US'],
            'LECTRA' => ['Lectra', 'FR'],
            'CUMMINS' => ['Cummins', 'US'],
            'PERKINS' => ['Perkins', 'GB'],
            'CATERPILLAR' => ['Caterpillar', 'US'],
            'ATLAS_COPCO' => ['Atlas Copco', 'SE'],
            'INGERSOLL_RAND' => ['Ingersoll Rand', 'US'],
            'OTHER' => ['Other', null],
        ];
    }

    public function run(): void
    {
        foreach (self::taxonomy() as $typeCode => [$typeName, $criticality, $categories]) {
            $type = AssetType::updateOrCreate(
                ['company_id' => null, 'code' => $typeCode],
                ['name' => $typeName, 'default_criticality' => $criticality],
            );

            foreach ($categories as $categoryCode => $categoryName) {
                AssetCategory::updateOrCreate(
                    ['company_id' => null, 'asset_type_id' => $type->id, 'code' => $categoryCode],
                    ['name' => $categoryName],
                );
            }
        }

        foreach (self::manufacturers() as $code => [$name, $country]) {
            Manufacturer::updateOrCreate(
                ['company_id' => null, 'code' => $code],
                ['name' => $name, 'country' => $country],
            );
        }
    }
}
