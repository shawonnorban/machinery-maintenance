<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\HrPayroll\Models\HrEmployee;
use App\Modules\HrPayroll\Models\HrEmployeeAssignment;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * HR & Payroll → Employee (AnnotechERP 24/03): Quick Registration numbers an
 * employee on save and splits Gross A and B by the gazette's structure, as
 * Brickland does (14,000 → Basic 7,700 + House Rent 3,850 + 450 + 1,250 +
 * 750); Employee List searches and filters; Employee Assignment posts one
 * employee or several from Company Management's places, and a transfer
 * closes the current posting today with its reason.
 */
class HrEmployeeTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    /** @var array<string, string> */
    private array $place = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $row = fn (string $table, array $data): string => tap((string) Str::ulid(), fn (string $id) => DB::table($table)->insert(['id' => $id, 'company_id' => $this->delta->id, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()] + $data));
        $building = $row('buildings', ['factory_id' => $factory->id, 'name' => 'Main Building', 'code' => 'B1']);
        $floor = $row('floors', ['building_id' => $building, 'name' => 'Sewing Floor', 'code' => 'F1']);
        $sewing = $row('departments', ['factory_id' => $factory->id, 'name' => 'Sewing', 'code' => 'SEW']);
        $store = $row('departments', ['factory_id' => $factory->id, 'name' => 'Store', 'code' => 'STR']);
        $section = $row('sections', ['department_id' => $sewing, 'name' => 'Sewing', 'code' => 'S1']);
        $this->place = [
            'factory_id' => $factory->id, 'building_id' => $building, 'floor_id' => $floor, 'department_id' => $sewing, 'section_id' => $section,
            'line_id' => $row('production_lines', ['department_id' => $sewing, 'section_id' => $section, 'floor_id' => $floor, 'name' => 'Line 1', 'code' => 'L1']),
            'designation_id' => $row('designations', ['department_id' => $sewing, 'name' => 'Jr. Operator', 'code' => 'JOP']),
            'store' => $store, 'store_designation' => $row('designations', ['department_id' => $store, 'name' => 'Receive Man', 'code' => 'RCV']),
        ];
    }

    public function test_quick_registration_numbers_an_employee_and_splits_the_gross_by_the_gazette(): void
    {
        $options = $this->as()->getJson('/api/v1/hr-payroll/employees/options')->assertOk()->json('data');
        $this->assertSame('EMP-000001', $options['next_code']);
        $worker = collect($options['structures'])->firstWhere('label', 'Worker — RMG Minimum Wage 2023 (Non-EPZ)');
        $this->assertNotNull($worker);
        $this->assertSame(['Main Building'], array_column($options['buildings'], 'label'));

        // Validation as Brickland words it.
        $this->as()->postJson('/api/v1/hr-payroll/employees', [])->assertUnprocessable()
            ->assertJsonPath('errors.card_no.0', 'Card / Proxi No. is required.')->assertJsonPath('errors.name.0', 'Employee Name is required.')
            ->assertJsonPath('errors.nid.0', 'NID No. is required.')->assertJsonPath('errors.structure.0', 'Salary Structure is required.');
        $form = ['card_no' => '10235', 'name' => 'Md. Ripon', 'nid' => '1234567890', 'joining_date' => '2026-10-01', 'mobile' => '01712345678', 'structure' => $worker['value'], 'gross_a' => 14000, 'gross_b' => 18000, 'actual_salary' => 15000];
        $this->as()->postJson('/api/v1/hr-payroll/employees', ['nid' => '12345'] + $form)->assertUnprocessable()->assertJsonPath('errors.nid.0', 'NID No. must be 10, 13 or 17 digits.');
        $this->as()->postJson('/api/v1/hr-payroll/employees', ['gross_a' => 2000] + $form)->assertUnprocessable()->assertJsonPath('errors.gross_a.0', 'The gross does not cover the fixed allowances of this structure.');

        $saved = $this->as()->postJson('/api/v1/hr-payroll/employees', $form)->assertCreated()->json('data');
        $this->assertEquals(['EMP-000001', 'ACTIVE', false, 'Worker — RMG Minimum Wage 2023 (Non-EPZ)', 14000], [$saved['code'], $saved['status'], $saved['assigned'], $saved['structure_label'], $saved['starting_salary']]);
        $this->assertSame(['basic' => 7700, 'house_rent' => 3850, 'conveyance' => 450, 'food' => 1250, 'medical' => 750, 'other' => 0, 'total' => 14000], $saved['split_a']);
        $this->assertSame([10367, 5183, 18000], [$saved['split_b']['basic'], $saved['split_b']['house_rent'], $saved['split_b']['total']]);
        $this->as()->postJson('/api/v1/hr-payroll/employees', ['name' => 'Someone Else'] + $form)->assertUnprocessable()->assertJsonPath('errors.card_no.0', 'Card / Proxi No. 10235 is already used by EMP-000001.');
        $this->assertSame('EMP-000002', $this->as()->getJson('/api/v1/hr-payroll/employees/options')->json('data.next_code'));

        // Edit: a changed salary is kept as a new record; the starting salary stays.
        $this->as()->putJson('/api/v1/hr-payroll/employees/EMP-000001', ['gross_a' => 15500] + $form)->assertOk()->assertJsonPath('data.gross_a', 15500)->assertJsonPath('data.starting_salary', 14000);
        $this->assertEquals([15500, 14000], array_column($this->as()->getJson('/api/v1/hr-payroll/employees/EMP-000001/history')->json('data.salaries'), 'gross_a'));

        // Employee List: searched by code, name, card, NID or mobile.
        $this->as()->postJson('/api/v1/hr-payroll/employees', ['card_no' => '10240', 'name' => 'Asia Begum', 'nid' => '1234567890123'] + $form)->assertCreated();
        $this->assertSame(['EMP-000002', 'EMP-000001'], array_column($this->as()->getJson('/api/v1/hr-payroll/employees')->json('data.rows'), 'code'));
        $this->assertSame(['EMP-000002'], array_column($this->as()->getJson('/api/v1/hr-payroll/employees?q=asia')->json('data.rows'), 'code'));
        $this->assertSame(['EMP-000001'], array_column($this->as()->getJson('/api/v1/hr-payroll/employees?q=10235')->json('data.rows'), 'code'));
        $this->assertSame(['pending' => 2, 'assigned' => 0, 'all' => 2], $this->as()->getJson('/api/v1/hr-payroll/employees')->json('data.counts'));

        // Who may: a factory manager reads, a storekeeper does not.
        $this->as(TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test'))->postJson('/api/v1/hr-payroll/employees', $form)->assertForbidden();
        $this->as(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test'))->getJson('/api/v1/hr-payroll/employees')->assertForbidden();
    }

    public function test_employee_assignment_posts_several_at_once_and_a_transfer_keeps_the_history(): void
    {
        $worker = collect($this->as()->getJson('/api/v1/hr-payroll/employees/options')->json('data.structures'))->firstWhere('label', 'Worker — RMG Minimum Wage 2023 (Non-EPZ)');
        foreach ([['10235', 'Md. Ripon'], ['10240', 'Asia Begum']] as [$card, $name]) {
            $this->as()->postJson('/api/v1/hr-payroll/employees', ['card_no' => $card, 'name' => $name, 'nid' => '1234567890', 'structure' => $worker['value'], 'gross_a' => 14000, 'gross_b' => 18000])->assertCreated();
        }
        $placement = array_diff_key($this->place, ['store' => 1, 'store_designation' => 1]);

        $this->as()->postJson('/api/v1/hr-payroll/employees/assign', [])->assertUnprocessable()
            ->assertJsonPath('errors.employees.0', 'Pick at least one employee.')->assertJsonPath('errors.factory_id.0', 'Factory is required.')
            ->assertJsonPath('errors.department_id.0', 'Department is required.')->assertJsonPath('errors.designation_id.0', 'Designation is required.');
        // Both at once; a first posting needs no reason.
        $this->as()->postJson('/api/v1/hr-payroll/employees/assign', ['employees' => ['EMP-000001', 'EMP-000002']] + $placement)->assertOk()->assertJsonPath('data.assigned', 2);
        $list = $this->as()->getJson('/api/v1/hr-payroll/employees?assignment=ASSIGNED')->json('data');
        $this->assertSame(['pending' => 0, 'assigned' => 2, 'all' => 2], $list['counts']);
        $this->assertSame(['Sewing', 'Line 1', 'Jr. Operator', 'Main Building', 'Sewing Floor'], [$list['rows'][0]['department'], $list['rows'][0]['line'], $list['rows'][0]['designation'], $list['rows'][0]['building'], $list['rows'][0]['floor']]);
        $this->assertCount(2, $this->as()->getJson('/api/v1/hr-payroll/employees?line='.$placement['line_id'])->json('data.rows'));

        // A transfer: its reason is required; the old posting closes today and stays in the history.
        $move = ['employees' => ['EMP-000001'], 'factory_id' => $placement['factory_id'], 'department_id' => $this->place['store'], 'designation_id' => $this->place['store_designation']];
        $this->as()->postJson('/api/v1/hr-payroll/employees/assign', $move)->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Transfer Reason is required.');
        $this->as()->postJson('/api/v1/hr-payroll/employees/assign', ['reason' => 'Moved to the fabric store'] + $move)->assertOk();
        $history = $this->as()->getJson('/api/v1/hr-payroll/employees/EMP-000001/history')->json('data');
        $this->assertSame([['Store', 'Receive Man', null, 'Moved to the fabric store'], ['Sewing', 'Jr. Operator', now()->toDateString(), null]], array_map(fn (array $a): array => [$a['department'], $a['designation'], $a['to'], $a['reason']], $history['assignments']));
        $this->assertNull($history['employee']['line']);
        $this->assertSame(1, HrEmployeeAssignment::query()->where('employee_id', HrEmployee::query()->where('employee_code', 'EMP-000001')->value('id'))->whereNull('effective_to')->count());

        // Places come from Company Management only — another company's is refused.
        $other = TenantFixture::company('Other Ltd', 'OTH');
        $this->as()->postJson('/api/v1/hr-payroll/employees/assign', ['employees' => ['EMP-000002'], 'factory_id' => TenantFixture::factory($other, 'Elsewhere', 'X1')->id] + $placement)->assertUnprocessable()->assertJsonValidationErrors('factory_id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
