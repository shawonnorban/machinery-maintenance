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
            ])
            ->assertCreated()
            ->assertJsonPath('data.employee_id', 'EMP-1001')
            ->assertJsonPath('data.status', 'ACTIVE');

        $technicianId = $created->json('data.id');

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

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
