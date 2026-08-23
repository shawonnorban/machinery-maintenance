<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Database\Seeders;

use App\Modules\Breakdown\Models\DowntimeReasonCode;
use App\Modules\Breakdown\Models\FailureCategory;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Breakdown\Models\RootCause;
use Illuminate\Database\Seeder;

/**
 * The failure vocabulary a garment maintenance team already uses
 * (Seed Catalog 3, 4, 5).
 *
 * Platform rows: null company_id, visible to every tenant, and a tenant may add
 * its own alongside them. Seeded rather than left to free text because free
 * text cannot be grouped, and "which failure keeps happening" is the question
 * the whole failure-analysis half of the product exists to answer.
 */
class FailureTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $this->categories();
        $this->rootCauses();
        $this->downtimeReasonCodes();
    }

    private function categories(): void
    {
        foreach ([
            ['MECHANICAL', 'Mechanical', 'যান্ত্রিক', $this->mechanical()],
            ['KNITTING', 'Knitting', 'নিটিং', $this->knitting()],
            ['DYEING_FINISHING', 'Dyeing and finishing', 'ডাইং ও ফিনিশিং', $this->dyeingAndFinishing()],
            ['ELECTRICAL', 'Electrical', 'বৈদ্যুতিক', $this->electrical()],
            ['PROCESS_CONTROL', 'Process and instrumentation', 'প্রসেস ও ইনস্ট্রুমেন্টেশন', $this->processControl()],
            ['PNEUMATIC_HYDRAULIC', 'Pneumatic and hydraulic', 'নিউম্যাটিক ও হাইড্রলিক', $this->pneumatic()],
            ['UTILITY_STEAM', 'Utility and steam', 'ইউটিলিটি ও স্টিম', $this->utility()],
            ['OPERATIONAL', 'Operational and other', 'পরিচালনাগত ও অন্যান্য', $this->operational()],
        ] as [$code, $name, $nameBn, $codes]) {
            $category = FailureCategory::updateOrCreate(
                ['company_id' => null, 'code' => $code],
                ['name' => $name, 'name_bn' => $nameBn, 'active' => true],
            );

            foreach ($codes as [$failureCode, $failureName, $failureNameBn]) {
                FailureCode::updateOrCreate(
                    ['company_id' => null, 'code' => $failureCode],
                    [
                        'failure_category_id' => $category->id,
                        'name' => $failureName,
                        'name_bn' => $failureNameBn,
                        'active' => true,
                    ],
                );
            }
        }
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function mechanical(): array
    {
        return [
            ['NEEDLE_BREAK', 'Needle breakage', 'সুই ভাঙা'],
            ['THREAD_BREAK', 'Thread breakage', 'সুতা ছেঁড়া'],
            ['HOOK_WORN', 'Rotary hook worn or damaged', 'হুক ক্ষয়'],
            ['FEED_DOG_WORN', 'Feed dog worn', 'ফিড ডগ ক্ষয়'],
            ['PRESSER_FOOT_FAULT', 'Presser foot misaligned or damaged', 'প্রেসার ফুট সমস্যা'],
            ['TIMING_OUT', 'Machine timing out of adjustment', 'টাইমিং সমস্যা'],
            ['TENSION_FAULT', 'Thread tension fault', 'টেনশন সমস্যা'],
            ['BOBBIN_CASE_FAULT', 'Bobbin case damaged', 'ববিন কেস সমস্যা'],
            ['BEARING_FAILURE', 'Bearing failure', 'বেয়ারিং নষ্ট'],
            ['BELT_BROKEN', 'Belt broken or slipping', 'বেল্ট ছেঁড়া'],
            ['GEAR_DAMAGE', 'Gear damage', 'গিয়ার ক্ষতি'],
            ['SHAFT_BENT', 'Shaft bent', 'শ্যাফট বাঁকা'],
            ['BLADE_BLUNT', 'Cutting blade blunt or chipped', 'ব্লেড ভোঁতা'],
            ['LUBRICATION_FAILURE', 'Lubrication failure or oil leak', 'তেল সমস্যা'],
            ['VIBRATION_ABNORMAL', 'Abnormal vibration or noise', 'অস্বাভাবিক কম্পন'],
        ];
    }

    /**
     * A knitting machine's own vocabulary (Seed Catalog 3).
     *
     * Kept apart from the general mechanical list because these are the codes
     * a knitting fitter actually says, and because grouping them is what makes
     * "which machine keeps dropping needles" answerable at all.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function knitting(): array
    {
        return [
            ['KNIT_NEEDLE_BROKEN', 'Knitting needle broken or bent', 'নিটিং সুই ভাঙা'],
            ['SINKER_BROKEN', 'Sinker broken or worn', 'সিংকার ভাঙা'],
            ['CAM_DAMAGE', 'Cam damaged or loose', 'ক্যাম ক্ষতিগ্রস্ত'],
            ['CYLINDER_DIAL_SCRATCH', 'Cylinder or dial scratched', 'সিলিন্ডার/ডায়াল আঁচড়'],
            ['YARN_FEEDER_FAULT', 'Yarn feeder fault', 'ইয়ার্ন ফিডার সমস্যা'],
            ['POSITIVE_FEEDER_BELT', 'Positive feeder belt broken or slipping', 'পজিটিভ ফিডার বেল্ট সমস্যা'],
            ['LYCRA_FEEDER_FAULT', 'Lycra feeder fault', 'লাইক্রা ফিডার সমস্যা'],
            ['STOP_MOTION_FAULT', 'Stop motion or yarn detector fault', 'স্টপ মোশন সমস্যা'],
            ['TAKE_DOWN_TENSION', 'Take-down tension fault', 'টেক-ডাউন টেনশন সমস্যা'],
            ['DROPPED_STITCH', 'Dropped stitch or hole in fabric', 'স্টিচ পড়ে যাওয়া'],
            ['OIL_PUMP_FAULT', 'Needle oil pump or spray fault', 'অয়েল পাম্প সমস্যা'],
            ['LINT_ACCUMULATION', 'Lint accumulation or blower fault', 'লিন্ট জমা / ব্লোয়ার সমস্যা'],
        ];
    }

    /**
     * The dye house and the finishing floor (Seed Catalog 3).
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function dyeingAndFinishing(): array
    {
        return [
            ['PUMP_SEAL_LEAK', 'Main pump seal leak', 'পাম্প সিল লিক'],
            ['IMPELLER_DAMAGE', 'Pump impeller damaged', 'ইম্পেলার ক্ষতিগ্রস্ত'],
            ['NOZZLE_BLOCKED', 'Dyeing nozzle blocked or worn', 'নোজল ব্লক'],
            ['REEL_DRIVE_FAULT', 'Reel or winch drive fault', 'রিল ড্রাইভ সমস্যা'],
            ['DOOR_SEAL_LEAK', 'Vessel door seal leak', 'ভেসেল ডোর সিল লিক'],
            ['ROPE_TANGLE', 'Fabric rope entangled in vessel', 'কাপড় পেঁচিয়ে যাওয়া'],
            ['HEAT_EXCHANGER_SCALING', 'Heat exchanger scaled or leaking', 'হিট এক্সচেঞ্জার স্কেলিং'],
            ['STENTER_CHAIN_FAULT', 'Stenter chain, clip or pin fault', 'স্টেন্টার চেইন সমস্যা'],
            ['STENTER_BURNER_FAULT', 'Stenter burner or heating fault', 'স্টেন্টার বার্নার সমস্যা'],
            ['PADDER_ROLLER_WEAR', 'Padder roller worn or uneven', 'প্যাডার রোলার ক্ষয়'],
            ['BLANKET_DAMAGE', 'Compactor blanket or felt damaged', 'কম্প্যাক্টর ব্ল্যাঙ্কেট ক্ষতি'],
            ['SELVEDGE_UNCURLER_FAULT', 'Selvedge uncurler fault', 'সেলভেজ আনকার্লার সমস্যা'],
            ['FABRIC_GUIDER_FAULT', 'Fabric guider or centring fault', 'ফেব্রিক গাইডার সমস্যা'],
            ['SHADE_VARIATION', 'Shade variation traced to the machine', 'মেশিনজনিত শেড ভ্যারিয়েশন'],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function electrical(): array
    {
        return [
            ['MOTOR_FAILURE', 'Motor failure', 'মোটর নষ্ট'],
            ['SERVO_FAULT', 'Servo motor or driver fault', 'সার্ভো সমস্যা'],
            ['CAPACITOR_FAILURE', 'Capacitor failure', 'ক্যাপাসিটর নষ্ট'],
            ['WIRING_FAULT', 'Wiring fault or short circuit', 'ওয়্যারিং সমস্যা'],
            ['SWITCH_FAULT', 'Switch or relay fault', 'সুইচ সমস্যা'],
            ['SENSOR_FAULT', 'Sensor fault', 'সেন্সর সমস্যা'],
            ['PCB_FAILURE', 'Control board failure', 'কন্ট্রোল বোর্ড নষ্ট'],
            ['DISPLAY_FAULT', 'Display or panel fault', 'ডিসপ্লে সমস্যা'],
            ['OVERHEATING', 'Overheating', 'অতিরিক্ত গরম'],
            ['POWER_FLUCTUATION_DAMAGE', 'Damage from voltage fluctuation', 'ভোল্টেজ সমস্যায় ক্ষতি'],
        ];
    }

    /**
     * Instrumentation, which in a dye house is not a footnote.
     *
     * A temperature probe reading two degrees low does not stop the machine;
     * it produces a batch in the wrong shade, and the loss is counted in
     * fabric rather than in downtime. Coding these separately is what lets a
     * dye house see that at all.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function processControl(): array
    {
        return [
            ['TEMP_SENSOR_DRIFT', 'Temperature sensor drift or failure', 'তাপমাত্রা সেন্সর ড্রিফট'],
            ['PH_PROBE_FAULT', 'pH probe fault', 'পিএইচ প্রোব সমস্যা'],
            ['FLOW_METER_FAULT', 'Flow meter fault', 'ফ্লো মিটার সমস্যা'],
            ['LEVEL_SENSOR_FAULT', 'Level sensor fault', 'লেভেল সেন্সর সমস্যা'],
            ['PRESSURE_TRANSMITTER_FAULT', 'Pressure transmitter fault', 'প্রেসার ট্রান্সমিটার সমস্যা'],
            ['DOSING_VALVE_FAULT', 'Dosing valve fault', 'ডোজিং ভালভ সমস্যা'],
            ['CONTROL_VALVE_PASSING', 'Control valve passing or stuck', 'কন্ট্রোল ভালভ পাসিং'],
            ['PLC_FAULT', 'PLC or process controller fault', 'পিএলসি সমস্যা'],
            ['RECIPE_DISPENSING_ERROR', 'Recipe or dispensing error', 'রেসিপি/ডিসপেন্সিং ভুল'],
            ['CALIBRATION_OVERDUE', 'Instrument out of calibration', 'ক্যালিব্রেশন মেয়াদোত্তীর্ণ'],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function pneumatic(): array
    {
        return [
            ['AIR_LEAK', 'Compressed air leak', 'বাতাস লিক'],
            ['CYLINDER_FAULT', 'Pneumatic cylinder fault', 'সিলিন্ডার সমস্যা'],
            ['SOLENOID_FAULT', 'Solenoid valve fault', 'সলিনয়েড ভালভ সমস্যা'],
            ['PRESSURE_LOW', 'Insufficient air pressure', 'প্রেসার কম'],
            ['HOSE_DAMAGE', 'Hose damaged', 'হোস নষ্ট'],
            ['HYDRAULIC_LEAK', 'Hydraulic oil leak', 'হাইড্রলিক লিক'],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function utility(): array
    {
        return [
            ['STEAM_LEAK', 'Steam leak', 'স্টিম লিক'],
            ['STEAM_TRAP_FAULT', 'Steam trap fault', 'স্টিম ট্র্যাপ সমস্যা'],
            ['BOILER_PRESSURE_FAULT', 'Boiler pressure abnormal', 'বয়লার প্রেসার সমস্যা'],
            ['WATER_LEVEL_FAULT', 'Water level control fault', 'পানির লেভেল সমস্যা'],
            ['FUEL_SUPPLY_FAULT', 'Fuel supply interruption', 'জ্বালানি সরবরাহ সমস্যা'],
            ['GENERATOR_START_FAILURE', 'Generator fails to start', 'জেনারেটর চালু হয় না'],
            ['COMPRESSOR_TRIP', 'Compressor tripped', 'কম্প্রেসার ট্রিপ'],
            ['CHILLER_TRIP', 'Chiller tripped', 'চিলার ট্রিপ'],
            ['THERMAL_OIL_FAULT', 'Thermal oil heater fault', 'থার্মাল অয়েল হিটার সমস্যা'],
            // Hardness or iron out of limit is a shade failure before it is a
            // machine failure, and the dye house is the first to notice it.
            ['WATER_QUALITY_FAULT', 'Treated water out of limit', 'পানির মান সীমার বাইরে'],
            ['SOFTENER_RESIN_EXHAUSTED', 'Softener resin exhausted', 'সফটেনার রেজিন শেষ'],
            ['ETP_DOSING_FAULT', 'ETP dosing or blower fault', 'ইটিপি ডোজিং সমস্যা'],
            ['CONDENSATE_RECOVERY_FAULT', 'Condensate recovery fault', 'কনডেনসেট রিকভারি সমস্যা'],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function operational(): array
    {
        return [
            ['OPERATOR_ERROR', 'Operator error', 'অপারেটর ভুল'],
            ['IMPROPER_SETTING', 'Incorrect machine setting', 'ভুল সেটিং'],
            ['MISSING_PM', 'Missed preventive maintenance', 'পিএম বাদ পড়া'],
            ['WRONG_SPARE_USED', 'Incorrect spare part fitted', 'ভুল পার্টস ব্যবহার'],
            ['MATERIAL_JAM', 'Fabric or material jam', 'কাপড় আটকে যাওয়া'],
            ['AGING', 'End of service life', 'মেয়াদ শেষ'],
            // Seeded deliberately. Without an honest option a technician under
            // pressure picks a wrong code, and wrong data is worse than absent
            // data. The share of UNKNOWN closures is itself a reportable
            // data-quality figure (Seed Catalog 3.5).
            ['UNKNOWN', 'Cause not determined', 'কারণ অজানা'],
        ];
    }

    private function rootCauses(): void
    {
        foreach ([
            ['NORMAL_WEAR', 'Normal wear and tear', 'স্বাভাবিক ক্ষয়'],
            ['INADEQUATE_LUBRICATION', 'Inadequate lubrication', 'পর্যাপ্ত তেল না দেওয়া'],
            // This one and POOR_QUALITY_SPARE most often justify the system's
            // own cost, so neither is left to free text.
            ['MISSED_MAINTENANCE', 'Preventive maintenance not performed', 'পিএম করা হয়নি'],
            ['IMPROPER_INSTALLATION', 'Improper installation or alignment', 'ভুল ইনস্টলেশন'],
            ['IMPROPER_OPERATION', 'Improper operation', 'ভুলভাবে চালানো'],
            ['INSUFFICIENT_TRAINING', 'Operator not adequately trained', 'প্রশিক্ষণের অভাব'],
            ['POOR_QUALITY_SPARE', 'Substandard spare part', 'নিম্নমানের পার্টস'],
            ['POWER_QUALITY', 'Voltage fluctuation or power quality', 'বিদ্যুতের মান'],
            ['ENVIRONMENTAL', 'Dust, humidity, or temperature', 'পরিবেশগত কারণ'],
            ['OVERLOAD', 'Machine run beyond rated capacity', 'অতিরিক্ত লোড'],
            ['DESIGN_LIMITATION', 'Design or model limitation', 'ডিজাইনের সীমাবদ্ধতা'],
            ['MANUFACTURING_DEFECT', 'Manufacturing defect', 'উৎপাদনগত ত্রুটি'],
            // Wet processing wears machines out in ways a sewing floor never
            // sees: scale, corrosion and chemical attack are the three that
            // account for most dye house replacements.
            ['SCALE_DEPOSIT', 'Scale or chemical deposit', 'স্কেল বা রাসায়নিক জমা'],
            ['CORROSION', 'Corrosion', 'ক্ষয়/মরিচা'],
            ['CHEMICAL_ATTACK', 'Chemical attack on components', 'রাসায়নিক ক্ষতি'],
            ['WATER_QUALITY', 'Water hardness or quality', 'পানির মান'],
            ['LINT_DUST_BUILDUP', 'Lint or dust build-up', 'লিন্ট/ধুলা জমা'],
            ['YARN_QUALITY', 'Poor yarn or fabric quality', 'সুতা/কাপড়ের নিম্নমান'],
            ['CALIBRATION_DRIFT', 'Instrument calibration drift', 'ক্যালিব্রেশন ড্রিফট'],
            ['END_OF_LIFE', 'Asset past useful life', 'মেয়াদোত্তীর্ণ'],
            ['UNDETERMINED', 'Not determined', 'নির্ণয় করা যায়নি'],
        ] as [$code, $name, $nameBn]) {
            RootCause::updateOrCreate(
                ['company_id' => null, 'code' => $code],
                ['name' => $name, 'name_bn' => $nameBn, 'active' => true],
            );
        }
    }

    private function downtimeReasonCodes(): void
    {
        foreach ([
            ['MACHINE_BREAKDOWN', 'UNPLANNED', true, 'Machine breakdown', 'মেশিন নষ্ট'],
            ['ELECTRICAL_FAULT', 'UNPLANNED', true, 'Electrical fault', 'বৈদ্যুতিক সমস্যা'],
            // Counting against availability is the point: it makes the cost of
            // an understocked store visible as downtime instead of hiding it
            // inside repair time (Seed Catalog 5).
            ['AWAITING_SPARE', 'UNPLANNED', true, 'Waiting for spare part', 'পার্টসের অপেক্ষা'],
            ['AWAITING_TECHNICIAN', 'UNPLANNED', true, 'Waiting for technician', 'টেকনিশিয়ানের অপেক্ষা'],
            ['SETTING_ADJUSTMENT', 'UNPLANNED', true, 'Machine setting or adjustment', 'সেটিং সমন্বয়'],
            ['PLANNED_PM', 'PLANNED', false, 'Scheduled preventive maintenance', 'নির্ধারিত পিএম'],
            ['PLANNED_OVERHAUL', 'PLANNED', false, 'Planned overhaul', 'পরিকল্পিত ওভারহল'],
            ['INSTALLATION', 'PLANNED', false, 'Installation or relocation', 'ইনস্টলেশন বা স্থানান্তর'],
            ['POWER_OUTAGE', 'EXTERNAL', false, 'Grid power outage', 'বিদ্যুৎ বিভ্রাট'],
            ['GAS_SUPPLY_FAILURE', 'EXTERNAL', false, 'Gas supply interruption', 'গ্যাস সরবরাহ বন্ধ'],
            ['MATERIAL_SHORTAGE', 'EXTERNAL', false, 'Material not available', 'কাঁচামাল নেই'],
            ['NO_OPERATOR', 'EXTERNAL', false, 'Operator not available', 'অপারেটর নেই'],
            ['STYLE_CHANGEOVER', 'EXTERNAL', false, 'Style or line changeover', 'স্টাইল পরিবর্তন'],
            // A dye house and a knitting floor stop for reasons a sewing floor
            // never does, and calling them all "changeover" hides where the
            // hours actually go.
            ['BATCH_CHANGEOVER', 'EXTERNAL', false, 'Batch or shade changeover', 'ব্যাচ/শেড পরিবর্তন'],
            ['QUALITY_CHANGEOVER', 'EXTERNAL', false, 'Yarn or fabric quality changeover', 'সুতা/কাপড় পরিবর্তন'],
            ['STEAM_UNAVAILABLE', 'EXTERNAL', false, 'Steam not available', 'স্টিম নেই'],
            ['WATER_UNAVAILABLE', 'EXTERNAL', false, 'Water supply interruption', 'পানি সরবরাহ বন্ধ'],
            ['EFFLUENT_LIMIT', 'EXTERNAL', false, 'Stopped for effluent limit', 'বর্জ্য সীমার কারণে বন্ধ'],
            ['SHIFT_BREAK', 'NON_OPERATING', false, 'Break or shift end', 'বিরতি বা শিফট শেষ'],
            ['HOLIDAY', 'NON_OPERATING', false, 'Factory holiday', 'কারখানা ছুটি'],
        ] as [$code, $class, $counts, $name, $nameBn]) {
            DowntimeReasonCode::updateOrCreate(
                ['company_id' => null, 'code' => $code],
                [
                    'name' => $name,
                    'name_bn' => $nameBn,
                    'downtime_class' => $class,
                    'counts_against_availability' => $counts,
                    'active' => true,
                ],
            );
        }
    }
}
