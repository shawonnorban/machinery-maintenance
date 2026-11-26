<?php

declare(strict_types=1);

namespace Tests\Feature\Planning;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\HrPayroll\Support\FactoryCalendarSetup;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\Tenancy\Models\ProductionLine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Capacity Planning → Master Data (AnnotechERP 12/01): the factory calendar's
 * weekly pattern and days of its own, planning lines on Company Management's
 * floors, the machines, roles and shifts under a line, efficiency standards
 * that name only what they apply to, style SMV / SAM, and the fixed lead
 * times — and who may change them.
 */
class PlanningMasterTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $unit;

    private Floor $floor;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit->id, 'name' => 'Main Building', 'code' => 'BLD-1']);
        $this->floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Floor 1', 'code' => 'FLR-001']);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_planning_lines_sit_on_a_floor_and_carry_their_machines_roles_and_shifts(): void
    {
        $department = Department::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit->id, 'name' => 'Sewing', 'code' => 'DPT-1']);
        $floorLine = ProductionLine::query()->create(['company_id' => $this->delta->id, 'department_id' => $department->id, 'floor_id' => $this->floor->id, 'code' => 'LN-001', 'name' => 'Line 1', 'line_type' => 'SEWING', 'status' => 'ACTIVE']);
        $line = ['code' => 'line01', 'name' => 'Line 01', 'std_manpower' => 50, 'std_machines' => 40, 'std_hours' => 10, 'std_efficiency' => 65];

        $this->as()->postJson('/api/v1/erp/planning-lines', $line)->assertUnprocessable()->assertJsonPath('errors.floor_id.0', 'Floor is required.');
        $id = $this->as()->postJson('/api/v1/erp/planning-lines', $line + ['floor_id' => $this->floor->id, 'floor_line_id' => $floorLine->id])->assertCreated()
            ->assertJsonPath('data.code', 'LINE01')->assertJsonPath('data.unit', 'FAC01')->assertJsonPath('data.floor', 'FLR-001')->assertJsonPath('data.floor_line', 'LN-001')->json('data.id');
        $this->as()->postJson('/api/v1/erp/planning-lines', $line + ['floor_id' => $this->floor->id])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->as()->postJson('/api/v1/erp/planning-lines', ['code' => 'L2', 'std_efficiency' => 160] + $line + ['floor_id' => $this->floor->id])->assertUnprocessable()->assertJsonValidationErrors('std_efficiency');
        $this->as()->getJson('/api/v1/planning/lookups')->assertOk()->assertJsonPath('data.lines.0.label', 'FLR-001>>LINE01')->assertJsonPath('data.floors.0.label', 'FAC01>>FLR-001');

        $this->as()->postJson('/api/v1/erp/planning-line-machines', ['planning_line_id' => $id, 'machine_type' => 'Single Needle', 'category' => 'SEWING', 'installed' => 10, 'working' => 8, 'standby' => 3])->assertUnprocessable()->assertJsonValidationErrors('working');
        $this->as()->postJson('/api/v1/erp/planning-line-machines', ['planning_line_id' => $id, 'machine_type' => 'Single Needle', 'category' => 'SEWING', 'installed' => 10, 'working' => 8, 'standby' => 2])->assertCreated()->assertJsonPath('data.planning_line', 'LINE01')->assertJsonPath('data.line', 'LN-001');

        $this->as()->postJson('/api/v1/erp/planning-line-manpower', ['planning_line_id' => $id, 'role' => 'OPERATOR', 'required' => 40])->assertCreated();
        $this->as()->postJson('/api/v1/erp/planning-line-manpower', ['planning_line_id' => $id, 'role' => 'OPERATOR', 'required' => 5])->assertUnprocessable()->assertJsonValidationErrors('role');

        // A line's shifts are HR & Payroll's: times and break come from the shift. A night shift runs past
        // midnight; a break as long as the shift is refused; the same shift twice on a line is refused.
        $dayShift = FactoryCalendarSetup::shift($this->delta->id, 'DAY', 'Day', '08:00', '17:00', 60);
        $nightShift = FactoryCalendarSetup::shift($this->delta->id, 'NIGHT', 'Night', '22:00', '06:00', 30);
        $shortShift = FactoryCalendarSetup::shift($this->delta->id, 'SHORT', 'Short', '08:00', '09:00', 60);
        $this->as()->postJson('/api/v1/erp/planning-line-shifts', ['planning_line_id' => $id, 'hr_shift_id' => $dayShift])->assertCreated()->assertJsonPath('data.working_hours', 8)->assertJsonPath('data.time', '08:00–17:00');
        $this->as()->postJson('/api/v1/erp/planning-line-shifts', ['planning_line_id' => $id, 'hr_shift_id' => $nightShift])->assertCreated()->assertJsonPath('data.working_hours', 7.5);
        $this->as()->postJson('/api/v1/erp/planning-line-shifts', ['planning_line_id' => $id, 'hr_shift_id' => $shortShift])->assertUnprocessable()->assertJsonValidationErrors('break_minutes');
        $this->as()->postJson('/api/v1/erp/planning-line-shifts', ['planning_line_id' => $id, 'hr_shift_id' => $dayShift])->assertUnprocessable()->assertJsonValidationErrors('hr_shift_id');
        $this->as()->getJson("/api/v1/erp/planning-line-shifts?filter[planning_line_id]={$id}")->assertJsonCount(2, 'data');

        // A line's machines, roles and shifts go with it.
        $this->as()->deleteJson("/api/v1/erp/planning-lines/{$id}")->assertNoContent();
        $this->as()->getJson('/api/v1/erp/planning-line-shifts')->assertJsonCount(0, 'data');
    }

    public function test_efficiency_standards_smv_and_lead_times(): void
    {
        $standard = ['applies_to' => 'UNIT', 'efficiency' => 62, 'effective_from' => '2026-01-01'];
        $this->as()->postJson('/api/v1/erp/planning-efficiencies', $standard)->assertUnprocessable()->assertJsonValidationErrors('factory_id');
        // A unit standard keeps only its unit, whatever else is sent.
        $this->as()->postJson('/api/v1/erp/planning-efficiencies', $standard + ['factory_id' => $this->unit->id, 'style_no' => 'ignored'])->assertCreated()->assertJsonPath('data.target', 'FAC01')->assertJsonPath('data.style_no', null);
        $this->as()->postJson('/api/v1/erp/planning-efficiencies', ['applies_to' => 'STYLE', 'style_no' => '867mi', 'efficiency' => 151, 'effective_from' => '2026-01-01'])->assertUnprocessable()->assertJsonPath('errors.efficiency.0', 'Efficiency must be between 0 and 150%.');
        $this->as()->postJson('/api/v1/erp/planning-efficiencies', ['applies_to' => 'STYLE', 'style_no' => '867mi', 'efficiency' => 70, 'effective_from' => '2026-01-01'])->assertCreated()->assertJsonPath('data.target', '867MI');

        $this->as()->postJson('/api/v1/erp/planning-style-smvs', ['style_no' => '531'])->assertUnprocessable()->assertJsonPath('errors.smv.0', 'Enter the SMV, the SAM or both.');
        $this->as()->postJson('/api/v1/erp/planning-style-smvs', ['style_no' => '531', 'sam' => 7.8])->assertCreated()->assertJsonPath('data.planning_source', 'SAM');
        $this->as()->postJson('/api/v1/erp/planning-style-smvs', ['style_no' => '867mi-05x', 'smv' => 8])->assertCreated()->assertJsonPath('data.style_no', '867MI-05X')->assertJsonPath('data.planning_source', 'SMV');
        $this->as()->postJson('/api/v1/erp/planning-style-smvs', ['style_no' => '867MI-05X', 'smv' => 9])->assertUnprocessable()->assertJsonValidationErrors('style_no');

        $leads = collect($this->as()->getJson('/api/v1/erp/planning-lead-times')->assertOk()->assertJsonCount(7, 'data')->json('data'));
        $cutting = $leads->firstWhere('activity', 'CUTTING');
        $this->assertSame([2, 'BEFORE_FIRST_SEWING', false], [$cutting['lead_days'], $cutting['counted_from'], $cutting['changed']]);
        $this->as()->patchJson("/api/v1/erp/planning-lead-times/{$cutting['id']}", ['lead_days' => 3])->assertOk()->assertJsonPath('data.changed', true);
        $this->as()->patchJson("/api/v1/erp/planning-lead-times/{$cutting['id']}", ['activity' => 'SEWING'])->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/planning-lead-times', ['activity' => 'SEWING', 'lead_days' => 1, 'counted_from' => 'BEFORE_CUTTING'])->assertUnprocessable();
        $this->as()->deleteJson("/api/v1/erp/planning-lead-times/{$cutting['id']}")->assertStatus(405);
    }

    public function test_who_may_change_the_planning_masters(): void
    {
        $chief = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($chief)->getJson('/api/v1/erp/planning-style-smvs')->assertOk();
        $this->withToken($chief)->postJson('/api/v1/erp/planning-style-smvs', ['style_no' => 'X1', 'smv' => 5])->assertForbidden();

        $manager = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'manager@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($manager)->postJson('/api/v1/erp/planning-style-smvs', ['style_no' => 'X1', 'smv' => 5])->assertCreated();

        $technician = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($technician)->getJson('/api/v1/planning/lookups')->assertForbidden();
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
