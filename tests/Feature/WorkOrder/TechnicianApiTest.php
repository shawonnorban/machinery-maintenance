<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Models\Technician;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The maintenance roster, over the API (API 16, SRS 25).
 */
class TechnicianApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private User $manager;

    private User $technicianRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->technicianRole = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech-login@delta.test');
        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
    }

    public function test_a_technician_can_be_created_updated_and_deactivated(): void
    {
        $created = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/technicians', [
                'name' => 'Karim Mia',
                'employee_id' => 'EMP-1001',
                'factory_id' => $this->dhaka->id,
                'specialization' => 'Sewing machines',
            ])
            ->assertCreated()
            ->assertJsonPath('data.employee_id', 'EMP-1001')
            ->assertJsonPath('data.status', 'ACTIVE');

        $technicianId = $created->json('data.id');

        // The roster list carries it too: its Specialization column read a field only the detail sent.
        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/technicians')
            ->assertOk()
            ->assertJsonPath('data.0.specialization', 'Sewing machines');

        // So an edit form can preselect these without a second round trip.
        $department = Department::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->dhaka->id, 'name' => 'Sewing', 'code' => 'SEW',
        ]);

        $this->withToken($this->tokenFor($this->manager))
            ->patchJson("/api/v1/technicians/{$technicianId}", [
                'name' => 'Karim Mia',
                'employee_id' => 'EMP-1001',
                'factory_id' => $this->dhaka->id,
                'department_id' => $department->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.department_id', $department->id);

        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/technicians/{$technicianId}")
            ->assertJsonPath('data.department_id', $department->id);

        $this->withToken($this->tokenFor($this->manager))
            ->patchJson("/api/v1/technicians/{$technicianId}", [
                'name' => 'Karim Mia (Senior)',
                'employee_id' => 'EMP-1001',
                'factory_id' => $this->dhaka->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Karim Mia (Senior)');

        $this->withToken($this->tokenFor($this->manager))
            ->patchJson("/api/v1/technicians/{$technicianId}/active", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.status', 'INACTIVE');
    }

    public function test_a_duplicate_employee_id_is_refused(): void
    {
        WorkOrderFixture::technician($this->delta, $this->dhaka, 'Karim Mia', 'EMP-1001');

        $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/technicians', [
                'name' => 'Rahim Uddin',
                'employee_id' => 'EMP-1001',
                'factory_id' => $this->dhaka->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('employee_id');
    }

    public function test_workload_reports_open_assignments_against_the_concurrency_limit(): void
    {
        $technician = WorkOrderFixture::technician(
            $this->delta, $this->dhaka, 'Karim Mia', 'EMP-1001', maxConcurrent: 1,
        );

        $workOrder = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Monthly service',
        ], $this->manager->id);

        app(AssignTechnicians::class)->handle($workOrder, [$technician->id], $this->manager->id);

        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/technicians/{$technician->id}/workload")
            ->assertOk()
            ->assertJsonPath('data.open_work_orders_count', 1)
            ->assertJsonPath('data.at_capacity', true)
            ->assertJsonPath('data.open_work_orders.0.work_order_number', $workOrder->work_order_number);
    }

    public function test_a_technician_with_history_cannot_be_deleted_but_an_unused_one_can(): void
    {
        $withHistory = WorkOrderFixture::technician($this->delta, $this->dhaka, 'Karim Mia', 'EMP-1001');

        $workOrder = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Monthly service',
        ], $this->manager->id);

        app(AssignTechnicians::class)->handle($workOrder, [$withHistory->id], $this->manager->id);

        $this->withToken($this->tokenFor($this->manager))
            ->deleteJson("/api/v1/technicians/{$withHistory->id}")
            ->assertStatus(409);

        $unused = WorkOrderFixture::technician($this->delta, $this->dhaka, 'Rahim Uddin', 'EMP-1002');

        $this->withToken($this->tokenFor($this->manager))
            ->deleteJson("/api/v1/technicians/{$unused->id}")
            ->assertNoContent();

        $this->assertNull(Technician::find($unused->id));
    }

    public function test_form_options_is_reachable_by_a_maintenance_manager_who_lacks_masterdata_manage(): void
    {
        // MAINTENANCE_MANAGER carries `technician.technician.manage` a tier
        // below where `settings.factory.manage`/`masterdata.manage` are
        // first granted (RoleSeeder's own $factoryManager tier) — the roster
        // screen's own dropdowns must not depend on either.
        $maintenanceManager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');

        $this->withToken($this->tokenFor($maintenanceManager))
            ->getJson('/api/v1/technicians/form-options')
            ->assertOk()
            ->assertJsonStructure(['data' => ['factories', 'departments', 'production_lines', 'users', 'proficiencies']])
            ->assertJsonPath('data.factories.0.id', $this->dhaka->id);
    }

    public function test_form_options_users_are_this_companys_own_active_members(): void
    {
        $response = $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/technicians/form-options')
            ->assertOk();

        $this->assertContains($this->technicianRole->id, array_column($response->json('data.users'), 'id'));
        $this->assertSame(['BASIC', 'INTERMEDIATE', 'EXPERT', 'CERTIFIED'], $response->json('data.proficiencies'));
    }

    public function test_a_skill_can_be_added_and_removed(): void
    {
        $technician = Technician::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->dhaka->id,
            'name' => 'Karim Mia',
            'employee_id' => 'EMP-2001',
            'status' => 'ACTIVE',
        ]);

        $added = $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/technicians/{$technician->id}/skills", [
                'skill_name' => 'Electrical panel repair',
                'proficiency' => 'EXPERT',
            ])
            ->assertCreated();

        $this->assertSame('Electrical panel repair', $added->json('data.skill_name'));

        $show = $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/technicians/{$technician->id}")
            ->assertOk();
        $this->assertCount(1, $show->json('data.skills'));

        // The same skill name twice is refused, not silently duplicated.
        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/technicians/{$technician->id}/skills", [
                'skill_name' => 'Electrical panel repair',
                'proficiency' => 'BASIC',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('skill_name');

        $skillId = $added->json('data.id');

        $this->withToken($this->tokenFor($this->manager))
            ->deleteJson("/api/v1/technicians/{$technician->id}/skills/{$skillId}")
            ->assertNoContent();

        $afterDelete = $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/technicians/{$technician->id}")
            ->assertOk();
        $this->assertCount(0, $afterDelete->json('data.skills'));
    }

    public function test_the_endpoints_are_closed_to_a_role_that_does_not_manage_the_roster(): void
    {
        $this->withToken($this->tokenFor($this->technicianRole))
            ->getJson('/api/v1/technicians')
            ->assertForbidden();
    }

    public function test_a_technician_is_picked_from_the_hr_employee_list(): void
    {
        $karim = $this->hrEmployee('EMP-000101', 'Karim Mechanic');
        $token = $this->tokenFor($this->manager);

        $options = $this->withToken($token)->getJson('/api/v1/technicians/form-options')->assertOk();
        $this->assertContains($karim, array_column($options->json('data.employees'), 'id'));

        // Name and ID come from HR, not from what the form sent.
        $created = $this->withToken($token)->postJson('/api/v1/technicians', [
            'hr_employee_id' => $karim,
            'name' => 'Typed over',
            'factory_id' => $this->dhaka->id,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Karim Mechanic')
            ->assertJsonPath('data.employee_id', 'EMP-000101')
            ->assertJsonPath('data.phone', '01710000101')
            ->assertJsonPath('data.hr_employee_id', $karim)
            ->assertJsonPath('data.hr_employee.employee_code', 'EMP-000101');

        // Already on the roster: not offered again as someone else's, and refused if sent.
        $again = $this->withToken($token)->getJson('/api/v1/technicians/form-options')->json('data.employees');
        $this->assertSame($created->json('data.id'), collect($again)->firstWhere('id', $karim)['technician_id']);

        $this->withToken($token)->postJson('/api/v1/technicians', [
            'hr_employee_id' => $karim,
            'factory_id' => $this->dhaka->id,
        ])->assertStatus(409)->assertJsonValidationErrors('hr_employee_id');

        // HR's later change reaches the roster on the next save.
        DB::table('hr_employees')->where('id', $karim)->update(['name' => 'Karim Uddin']);
        $this->withToken($token)->patchJson('/api/v1/technicians/'.$created->json('data.id'), [
            'hr_employee_id' => $karim,
            'factory_id' => $this->dhaka->id,
        ])->assertOk()->assertJsonPath('data.name', 'Karim Uddin');
    }

    public function test_a_separated_employee_cannot_be_put_on_the_roster(): void
    {
        $gone = $this->hrEmployee('EMP-000102', 'Left Last Month', 'SEPARATED');

        $this->withToken($this->tokenFor($this->manager))->postJson('/api/v1/technicians', [
            'hr_employee_id' => $gone,
            'factory_id' => $this->dhaka->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('hr_employee_id');
    }

    /** An HR & Payroll employee of Delta, posted to Dhaka: the least HR needs (a gazette and an employment type). */
    private function hrEmployee(string $code, string $name, string $status = 'ACTIVE'): string
    {
        $now = now();
        $gazette = DB::table('hr_gazettes')->where('company_id', $this->delta->id)->value('id');
        if ($gazette === null) {
            $gazette = (string) Str::ulid();
            DB::table('hr_gazettes')->insert(['id' => $gazette, 'company_id' => $this->delta->id, 'name' => 'RMG 2023', 'effective_date' => '2023-12-01', 'created_at' => $now, 'updated_at' => $now]);
        }
        $type = DB::table('hr_employment_types')->where('company_id', $this->delta->id)->value('id');
        if ($type === null) {
            $type = (string) Str::ulid();
            DB::table('hr_employment_types')->insert(['id' => $type, 'company_id' => $this->delta->id, 'name' => 'Worker', 'name_bn' => 'শ্রমিক', 'short_name' => 'WRK', 'created_at' => $now, 'updated_at' => $now]);
        }

        $id = (string) Str::ulid();
        DB::table('hr_employees')->insert([
            'id' => $id, 'company_id' => $this->delta->id, 'employee_code' => $code, 'card_no' => 'C'.substr($code, -4),
            'name' => $name, 'nid' => '1234567890', 'mobile' => '01710'.substr($code, -6), 'status' => $status,
            'gazette_id' => $gazette, 'employment_type_id' => $type, 'gross_a' => 14000, 'gross_b' => 18000,
            'factory_id' => $this->dhaka->id, 'joining_date' => '2025-02-01', 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $id;
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
