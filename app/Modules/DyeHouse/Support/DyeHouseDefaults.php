<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Support;

use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\DyeHouse\Models\DyeMachineList;
use App\Modules\DyeHouse\Models\DyeMethod;

/**
 * The dye house lists a company starts with, as Brickland ships them (Dye
 * House → Master Data, AnnotechERP 17/01): eight dyeing methods, the machine
 * types, brands and locations, and ten colour groups. Each is made the first
 * time its list is opened, and only into an empty list.
 */
final class DyeHouseDefaults
{
    /** name => [short name, single/double, default?, active?] */
    public const METHODS = [
        '2 Part Dye' => ['2P Dye', 'SINGLE', false, true], 'Discharge' => ['Disch', 'SINGLE', false, true], 'Dispersed' => ['Disp', 'SINGLE', false, true],
        'N/A' => ['N/A', 'SINGLE', false, true], 'Pigment' => ['Pigm', 'SINGLE', false, true], 'Reactive' => ['React', 'SINGLE', false, true],
        'Wht. Dyeing' => ['Wht', 'SINGLE', false, true], 'Normal Wash' => ['N/Wash', 'SINGLE', false, false],
    ];

    /** list => names */
    public const MACHINE_LISTS = [
        'TYPE' => ['High Pressure', 'Atmospheric', 'Soft Flow', 'Sample Dyeing', 'Winch'],
        'BRAND' => ['Fongs', 'PMM Moon star', 'Thies', 'Sclavos', 'Dilmenler'],
        'LOCATION' => ['Dyeing Shed-1', 'Dyeing Shed-2', 'Sample House', 'Finishing Bay'],
    ];

    /** code => [name, lab dip?, display order, active?] */
    public const COLOUR_GROUPS = [
        '1' => ['Mellange/YD', true, 9, true], '3' => ['Light-2', false, 0, false], '4' => ['White', true, 1, true], '5' => ['Light', true, 2, true],
        '6' => ['Medium', true, 3, true], '7' => ['Dark', true, 4, true], '8' => ['Extra Dark', true, 5, true], '9' => ['Black/Royal Blue', true, 11, true],
        '10' => ['N/A', false, 0, false], '11' => ['PC/CVC', true, 10, true],
    ];

    public static function methods(string $companyId): void
    {
        if (DyeMethod::query()->exists()) {
            return;
        }
        foreach (self::METHODS as $name => [$short, $sd, $default, $active]) {
            DyeMethod::query()->create(['company_id' => $companyId, 'name' => $name, 'short_name' => $short, 'sd' => $sd, 'is_default' => $default, 'active' => $active]);
        }
    }

    public static function machineLists(string $companyId): void
    {
        if (DyeMachineList::query()->exists()) {
            return;
        }
        foreach (self::MACHINE_LISTS as $list => $names) {
            foreach ($names as $i => $name) {
                DyeMachineList::query()->create(['company_id' => $companyId, 'list' => $list, 'name' => $name, 'sort_order' => ($i + 1) * 10, 'active' => true]);
            }
        }
    }

    public static function colourGroups(string $companyId): void
    {
        if (DyeColourGroup::query()->exists()) {
            return;
        }
        foreach (self::COLOUR_GROUPS as $code => [$name, $labdip, $order, $active]) {
            DyeColourGroup::query()->create(['company_id' => $companyId, 'code' => (string) $code, 'name' => $name, 'labdip_required' => $labdip, 'water_cost' => 0, 'wash_cost' => 0, 'display_order' => $order, 'active' => $active]);
        }
    }

    /** A pass code as stored: keyed to the company, never the digits themselves. */
    public static function passCodeHash(string $companyId, string $code): string
    {
        return hash_hmac('sha256', $companyId.'|'.$code, (string) config('app.key'));
    }
}
