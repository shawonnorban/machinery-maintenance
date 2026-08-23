<?php

declare(strict_types=1);

namespace App\Modules\Asset\Database\Seeders;

use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Asset\Models\Manufacturer;
use Illuminate\Database\Seeder;

/**
 * The textile industry asset taxonomy (Seed Catalog 2).
 *
 * Covers the whole mill, not only the sewing floor: yarn preparation, knitting,
 * dyeing, fabric finishing, cutting, sewing, garment washing, printing,
 * embroidery, the lab, and the utilities that feed all of it. A composite
 * factory runs every one of these under one roof, and a maintenance system that
 * only knows about sewing machines is a system the dye house keeps its own
 * spreadsheet beside.
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
            // ---- Yarn and knitting -------------------------------------
            'YARN_PREP' => ['Yarn Preparation', 'MEDIUM', [
                'SOFT_WINDING' => 'Soft Package Winding',
                'REWINDING' => 'Rewinding',
                'YARN_CONDITIONING' => 'Yarn Conditioning',
                'WARPING' => 'Warping',
                'YARN_CLEARER' => 'Yarn Clearer Unit',
            ]],
            // A knitting machine stopped is a fabric order stopped, and a
            // dropped stitch found three days later is a roll rejected.
            'KNITTING' => ['Knitting', 'HIGH', [
                'CIRCULAR_SINGLE_JERSEY' => 'Circular Single Jersey',
                'CIRCULAR_INTERLOCK' => 'Circular Interlock',
                'CIRCULAR_RIB' => 'Circular Rib',
                'FLEECE_TERRY' => 'Fleece and Terry',
                'CIRCULAR_JACQUARD' => 'Circular Jacquard',
                'FLAT_KNITTING' => 'Flat Bed Knitting',
                'COLLAR_CUFF' => 'Collar and Cuff',
                'WARP_KNITTING' => 'Warp Knitting',
                'SEAMLESS_KNITTING' => 'Seamless and Bodysize',
                'SOCKS_KNITTING' => 'Socks Knitting',
                'LYCRA_FEEDER_UNIT' => 'Lycra Feeder Unit',
            ]],
            // ---- Dyeing --------------------------------------------------
            // CRITICAL by default, and not because the machine is expensive: a
            // dye vessel that stops mid-batch does not pause, it ruins the
            // batch inside it.
            'DYEING' => ['Fabric and Yarn Dyeing', 'CRITICAL', [
                'SOFT_FLOW_DYEING' => 'Soft Flow Dyeing',
                'HT_HP_DYEING' => 'HT/HP Dyeing',
                'JET_DYEING' => 'Jet Dyeing',
                'WINCH_DYEING' => 'Winch Dyeing',
                'JIGGER_DYEING' => 'Jigger Dyeing',
                'PAD_BATCH' => 'Cold Pad Batch',
                'CONTINUOUS_RANGE' => 'Continuous Dyeing Range',
                'SCOURING_BLEACHING' => 'Scouring and Bleaching',
                'YARN_PACKAGE_DYEING' => 'Package (Yarn) Dyeing',
                'HANK_DYEING' => 'Hank Dyeing',
                'RF_DRYER' => 'RF Dryer',
                'COLOR_KITCHEN' => 'Colour Kitchen and Dispenser',
                'DOSING_UNIT' => 'Chemical Dosing Unit',
            ]],
            // ---- Fabric finishing ---------------------------------------
            'FABRIC_FINISHING' => ['Fabric Finishing', 'HIGH', [
                'STENTER' => 'Stenter and Heat Setting',
                'COMPACTOR_OPEN' => 'Open Compactor',
                'COMPACTOR_TUBULAR' => 'Tubular Compactor',
                'DEWATERING' => 'Dewatering and Squeezer',
                'SLITTING' => 'Slitting Machine',
                'RELAX_DRYER' => 'Relax Dryer',
                'FABRIC_TUMBLE_DRYER' => 'Fabric Tumble Dryer',
                'CALENDER' => 'Calender',
                'RAISING' => 'Raising and Brushing',
                'SUEDING' => 'Sueding and Peaching',
                'SHEARING' => 'Shearing',
                'SANFORIZING' => 'Sanforizing',
                'FABRIC_INSPECTION' => 'Fabric Inspection Machine',
                'FABRIC_ROLLING' => 'Rolling and Batching',
                'BALING_PRESS' => 'Baling Press',
            ]],
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
            // Garment-level wet processing only. Fabric and yarn dyeing moved
            // to their own type, because a dye house is not a washing plant
            // with extra vessels: different machines, different failures,
            // different people answering for them.
            'WET_PROCESS' => ['Garment Washing', 'HIGH', [
                'WASHING_MACHINE' => 'Washing Machine',
                'HYDRO_EXTRACTOR' => 'Hydro Extractor',
                'TUMBLE_DRYER' => 'Tumble Dryer',
                'GARMENT_DYEING' => 'Garment Dyeing',
                'CURING_OVEN' => 'Curing Oven',
                'SANDBLAST_CABIN' => 'Sandblast Cabin',
                'OZONE_MACHINE' => 'Ozone Machine',
                'LASER_MACHINE' => 'Laser Whiskering Machine',
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
                'THERMAL_OIL_HEATER' => 'Thermal Oil Heater',
                'CONDENSATE_RECOVERY' => 'Condensate Recovery Unit',
                'GENERATOR' => 'Generator',
                'GAS_BOOSTER' => 'Gas Booster',
                'AIR_COMPRESSOR' => 'Air Compressor',
                'AIR_DRYER' => 'Air Dryer',
                'CHILLER' => 'Chiller',
                'COOLING_TOWER' => 'Cooling Tower',
                'WATER_PUMP' => 'Water Pump',
                'DEEP_TUBEWELL' => 'Deep Tubewell',
                // A dye house lives or dies by its water: hardness or iron out
                // of limit is a shade failure before it is a machine failure.
                'WTP_UNIT' => 'Water Treatment Plant',
                'SOFTENER_PLANT' => 'Water Softener Plant',
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
                // The dye house lab. A spectrophotometer out of calibration
                // passes shades that the buyer's lab then rejects, which is a
                // maintenance failure with a container-sized invoice behind it.
                'SPECTROPHOTOMETER' => 'Spectrophotometer',
                'LAB_DYEING' => 'Lab and Sample Dyeing Machine',
                'LAB_STENTER' => 'Lab Stenter and Curing Oven',
                'WASH_FASTNESS_TESTER' => 'Wash Fastness Tester',
                'PILLING_TESTER' => 'Pilling Tester',
                'SHRINKAGE_DRYER' => 'Shrinkage Test Dryer',
                'PH_METER' => 'pH Meter',
            ]],
        ];
    }

    /**
     * Manufacturers a Bangladeshi textile mill actually has on the floor.
     *
     * Grouped by the part of the mill that buys them, because almost nobody
     * buys across the groups: the sewing floor's supplier list and the dye
     * house's have nothing in common but the generator.
     *
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function manufacturers(): array
    {
        return [
            // Knitting
            'MAYER_CIE' => ['Mayer & Cie', 'DE'],
            'TERROT' => ['Terrot', 'DE'],
            'FUKUHARA' => ['Fukuhara', 'JP'],
            'PAILUNG' => ['Pai Lung', 'TW'],
            'ORIZIO' => ['Orizio', 'IT'],
            'SANTONI' => ['Santoni', 'IT'],
            'LONATI' => ['Lonati', 'IT'],
            'SHIMA_SEIKI' => ['Shima Seiki', 'JP'],
            'STOLL' => ['Stoll', 'DE'],
            'KARL_MAYER' => ['Karl Mayer', 'DE'],
            'WELLKNIT' => ['Wellknit', 'TW'],

            // Dyeing
            'THIES' => ['Thies', 'DE'],
            'FONGS' => ['Fong’s', 'HK'],
            'SCLAVOS' => ['Sclavos', 'GR'],
            'DILMENLER' => ['Dilmenler', 'TR'],
            'THEN' => ['Then', 'DE'],
            'BRAZZOLI' => ['Brazzoli', 'IT'],
            'LORIS_BELLINI' => ['Loris Bellini', 'IT'],
            'MCS' => ['MCS', 'IT'],
            'TONG_GENG' => ['Tong Geng', 'TW'],
            'SEDO_TREEPOINT' => ['Sedo Treepoint', 'DE'],
            'LAWER' => ['Lawer', 'IT'],

            // Fabric finishing
            'MONFORTS' => ['Monforts', 'DE'],
            'BRUCKNER' => ['Brückner', 'DE'],
            'SANTEX' => ['Santex', 'CH'],
            'TUBE_TEX' => ['Tube-Tex', 'US'],
            'LAFER' => ['Lafer', 'IT'],
            'FERRARO' => ['Ferraro', 'IT'],
            'CORINO' => ['Corino', 'IT'],
            'BIANCO' => ['Bianco', 'IT'],

            // Laboratory
            'DATACOLOR' => ['Datacolor', 'US'],
            'X_RITE' => ['X-Rite', 'US'],
            'JAMES_HEAL' => ['James Heal', 'GB'],
            'SDL_ATLAS' => ['SDL Atlas', 'US'],
            'MATHIS' => ['Mathis', 'CH'],

            // Sewing, cutting and garment finishing
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
            // Utilities
            'CUMMINS' => ['Cummins', 'US'],
            'PERKINS' => ['Perkins', 'GB'],
            'CATERPILLAR' => ['Caterpillar', 'US'],
            'ATLAS_COPCO' => ['Atlas Copco', 'SE'],
            'INGERSOLL_RAND' => ['Ingersoll Rand', 'US'],
            'KAESER' => ['Kaeser', 'DE'],
            'THERMAX' => ['Thermax', 'IN'],
            'COCHRAN' => ['Cochran', 'GB'],
            'GRUNDFOS' => ['Grundfos', 'DK'],
            'ALFA_LAVAL' => ['Alfa Laval', 'SE'],

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

            $this->retireCategoriesRemovedFrom($type->id, array_keys($categories));
        }

        foreach (self::manufacturers() as $code => [$name, $country]) {
            Manufacturer::updateOrCreate(
                ['company_id' => null, 'code' => $code],
                ['name' => $name, 'country' => $country],
            );
        }
    }

    /**
     * Drop platform categories this catalogue no longer lists.
     *
     * Fabric dyeing used to sit under garment washing, and leaving the old
     * rows behind would give every tenant two places to file the same machine
     * — which is exactly the ambiguity the seeded taxonomy exists to prevent.
     *
     * Only platform rows, and only ones nothing is filed under. A category
     * with assets in it is somebody's history, and a seeder is never the right
     * thing to delete history: it is left in place, and a person decides.
     *
     * @param  list<string>  $keep
     */
    private function retireCategoriesRemovedFrom(string $typeId, array $keep): void
    {
        AssetCategory::query()
            ->whereNull('company_id')
            ->where('asset_type_id', $typeId)
            ->whereNotIn('code', $keep)
            ->whereDoesntHave('assets')
            ->delete();
    }
}
