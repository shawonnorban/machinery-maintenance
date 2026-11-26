<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Database\Seeders;

use App\Modules\DyeHouse\Models\DyeDosingTemplate;
use App\Modules\DyeHouse\Models\DyeDosingTemplateAgent;
use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;
use Illuminate\Support\Facades\DB;

/** Dye House → Master Data sample rows (AnnotechERP 17/01): finishing-machine brands, the dyeing machines from bulk to lab, and who operates and inspects. Methods, colour groups and the machine lists ship as defaults. Lab-Dip (17/02): Brickland's dosing templates, the conventional reactive curve with its eight agents. */
final class DyeHouseMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        foreach (['dye-methods', 'dye-colour-groups', 'dye-machine-lists'] as $key) {
            $s->ensure($key);
        }
        $s->seed('dye-machine-lists', array_map(fn (array $l, int $i) => ['list' => $l[0], 'name' => $l[1], 'sort_order' => 100 + $i], [
            ['BRAND', 'Brückner'], ['BRAND', 'Lafer'], ['BRAND', 'Tubetex'], ['BRAND', 'Corino'], ['TYPE', 'Jet Dyeing'],
        ], [0, 1, 2, 3, 4]), ['name']);

        $list = fn (ResourceSeeder $s, string $name) => $s->id('dye-machine-lists', ['name' => $name]);
        $origin = fn (ResourceSeeder $s, string $code) => $s->id('origin-countries', ['code' => $code]);
        $floor = $this->floorId('Building B', 'Ground Floor');
        $machines = [
            ['DM-01', 'ECO-38', 'Soft Flow', 'Fongs', 'Dyeing Shed-1', '156', 1500, 'FG-2019-0381', 2019, false],
            ['DM-02', 'ECO-38', 'Soft Flow', 'Fongs', 'Dyeing Shed-1', '156', 1000, 'FG-2019-0382', 2019, false],
            ['DM-03', 'Moonstar HT', 'High Pressure', 'PMM Moon star', 'Dyeing Shed-1', '158', 750, 'PMM-2020-1145', 2020, false],
            ['DM-04', 'Supra', 'Soft Flow', 'Thies', 'Dyeing Shed-2', '276', 500, 'TH-2018-7721', 2018, false],
            ['DM-05', 'Supra', 'Soft Flow', 'Thies', 'Dyeing Shed-2', '276', 250, 'TH-2018-7722', 2018, false],
            ['DM-06', 'Dilmenler DMS', 'Atmospheric', 'Dilmenler', 'Dyeing Shed-2', '792', 100, 'DL-2021-0456', 2021, false],
            ['SM-01', 'Sample 10', 'Sample Dyeing', 'Fongs', 'Sample House', '156', 10, 'FG-2022-S011', 2022, true],
            ['SM-02', 'Sample 25', 'Sample Dyeing', 'Sclavos', 'Sample House', '380', 25, 'SC-2022-S025', 2022, true],
        ];
        $s->seed('dye-machines', array_map(fn (array $m) => fn (ResourceSeeder $s) => [
            'machine_no' => $m[0], 'model' => $m[1], 'machine_type_id' => $list($s, $m[2]), 'brand_id' => $list($s, $m[3]), 'location_id' => $list($s, $m[4]),
            'origin_country_id' => $origin($s, $m[5]), 'capacity' => $m[6], 'capacity_unit' => 'KG', 'efficiency' => 85, 'sl_no' => $m[7], 'year' => $m[8],
            'rpm' => 300, 'temperature' => $m[2] === 'High Pressure' ? 140 : 98, 'nozzles' => $m[6] >= 500 ? 4 : 1, 'is_sample' => $m[9], 'floor_id' => $floor, 'mc_version' => '1', 'prod_status' => 'FREE',
        ], $machines), ['machine_no']);

        $people = [
            ['DF_PROCESS_OPERATOR', 'Karim Mia', 'Dyeing Department', 'Senior Operator', '4101'],
            ['DF_PROCESS_OPERATOR', 'Jashim Uddin', 'Dyeing Department', 'Operator', '4102'],
            ['DF_QUALITY_INSPECTION', 'Nasrin Akter', 'Quality Assurance / Quality Control', 'QC Inspector', '4201'],
            ['GFAB_PROCESS_OPERATOR', 'Sabbir Ahmed', 'Fabric Finishing Department', 'Operator', '4301'],
            ['GFAB_QUALITY_INSPECTION', 'Farhana Islam', 'Quality Assurance / Quality Control', 'QC Inspector', '4401'],
        ];
        $s->seed('dye-responsible-employees', array_map(fn (array $p, int $i) => fn () => [
            'employee_type' => $p[0], 'user_id' => $this->userId($p[1], $i), 'employee_name' => $p[1], 'department' => $p[2], 'designation' => $p[3], 'pass_code' => $p[4],
        ], $people, array_keys($people)), ['user_id']);

        $this->templates();
    }

    /** name => [short name, method, colour group, type, finalised?] — Brickland's dosing templates. */
    private const TEMPLATES = [
        'CONVENTIONAL FOR REACTIVE' => ['CONV/REACT', 'Reactive', 'Medium', 'DYEING'],
        'BIO-SCOURING FOR REACTIVE' => ['BIO/REACT', 'Reactive', 'Medium', 'PRE_TREATMENT'],
        'DEMINERALIZATION FOR REACTIVE' => ['DEMIN/REACT', 'Reactive', 'Medium', 'PRE_TREATMENT'],
        'WHITE COTTON (OBA)' => ['WHT/OBA', 'Wht. Dyeing', 'White', 'DYEING'],
        'WHITE PC/CVC (TWO PART)' => ['WHT/PC', '2 Part Dye', 'White', 'DYEING'],
        'CONVENTIONAL FOR DISPERSED' => ['CONV/DISP', 'Dispersed', 'Medium', 'DYEING'],
        'BIO-SCOURING FOR DISPERSED' => ['BIO/DISP', 'Dispersed', 'Medium', 'PRE_TREATMENT'],
        'DEMINERALIZATION FOR DISPERSED' => ['DEMIN/DISP', 'Dispersed', 'Medium', 'PRE_TREATMENT'],
        '2 PART DYEING CONVENTIONAL' => ['2PART/CONV', '2 Part Dye', 'Dark', 'DYEING'],
        '2 PART FIRST SCOURING' => ['2PART/SCOUR', '2 Part Dye', 'Dark', 'PRE_TREATMENT'],
        'MELLANGE AND Y/D WASH' => ['MEL/YD', 'Reactive', 'Mellange/YD', 'WASH'],
        'MACHINE WASH' => ['MC/WASH', 'N/A', 'Medium', 'WASH'],
        'FINISHING TEMPLATE' => ['FIN', 'N/A', 'Medium', 'FINISHING'],
        'DEMINERALIZATION FOR PIGMENT' => ['DEMIN/PIGM', 'Pigment', 'Medium', 'PRE_TREATMENT'],
        'LAB-DIP TEMPLATE' => ['LAB/DIP', 'Reactive', 'Medium', 'DYEING'],
    ];

    /** step => [phase, process, chemical (name it starts with), lab dip?, line break?, dose, unit] — the conventional reactive curve. */
    private const REACTIVE_CURVE = [
        1 => ['PRE_TREATMENT', 'DEMINERALISATION', 'SEQUESTERING', true, false, 1.0, 'KG'],
        2 => ['PRE_TREATMENT', 'SCOURING', 'CAUSTIC SODA', true, false, 2.0, 'KG'],
        3 => ['PRE_TREATMENT', 'HOT_WASH', 'ACETIC ACID', true, true, 0.8, 'LTR'],
        4 => ['DYEING', 'LEVELLING', 'LEVELLING', true, false, 1.0, 'KG'],
        5 => ['DYEING', 'SALT_DOSING', 'GLAUBER', true, false, 60.0, 'KG'],
        6 => ['DYEING', 'ALKALI_DOSING', 'SODA ASH', true, false, 15.0, 'KG'],
        7 => ['AFTER_TREATMENT', 'SOAPING', 'SOAPING', true, true, 1.0, 'KG'],
        8 => ['FINISHING', 'SOFTENING', 'CATIONIC SOFTENER', false, false, 2.0, 'KG'],
    ];

    /** Made straight, as the template screen saves them: a header, its code, and its agents in step order. Only a name not yet on file. */
    private function templates(): void
    {
        $company = $this->companyId();
        $methods = DB::table('dye_methods')->where('company_id', $company)->pluck('id', 'name');
        $groups = DB::table('dye_colour_groups')->where('company_id', $company)->pluck('id', 'name');
        $department = DB::table('item_departments')->where('company_id', $company)->where('code', 'DYES_CHEMICAL_STORE')->value('id');
        $n = (int) DyeDosingTemplate::query()->where('code', 'like', 'TPL-%')->pluck('code')->map(fn ($c) => (int) substr($c, 4))->max();
        foreach (self::TEMPLATES as $name => [$short, $method, $group, $type]) {
            if (DyeDosingTemplate::query()->where('name', $name)->exists() || ! isset($methods[$method], $groups[$group])) {
                continue;
            }
            $template = DyeDosingTemplate::query()->create([
                'company_id' => $company, 'code' => sprintf('TPL-%04d', ++$n), 'name' => $name, 'short_name' => $short,
                'dye_method_id' => $methods[$method], 'colour_group_id' => $groups[$group], 'template_type' => $type, 'is_finalized' => true,
            ]);
            if ($name !== 'CONVENTIONAL FOR REACTIVE' || ! $department) {
                continue;
            }
            foreach (self::REACTIVE_CURVE as $step => [$phase, $process, $chemical, $labdip, $break, $dose, $uom]) {
                $item = DB::table('items')->where('company_id', $company)->where('item_department_id', $department)->where('active', true)->where('name', 'ilike', $chemical.'%')->orderBy('name')->value('id');
                if ($item) {
                    DyeDosingTemplateAgent::query()->create([
                        'company_id' => $company, 'template_id' => $template->id, 'step_no' => $step, 'dye_method_id' => $methods[$method], 'phase' => $phase, 'process' => $process,
                        'item_id' => $item, 'is_labdip' => $labdip, 'is_line_break' => $break, 'dose' => $dose, 'uom' => $uom,
                    ]);
                }
            }
        }
    }
}
