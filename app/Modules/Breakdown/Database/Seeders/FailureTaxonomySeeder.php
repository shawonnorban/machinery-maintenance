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
            ['ELECTRICAL', 'Electrical', 'বৈদ্যুতিক', $this->electrical()],
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
