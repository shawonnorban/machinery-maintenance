<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\WorkOrder\Models\Technician;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The maintenance roster (SRS 25).
 *
 * Who is on the floor and what they look after. A dyeing technician covers the
 * dye house, a sewing mechanic the sewing floor — and none of it carries money,
 * because these are salaried employees.
 */
class TechnicianRosterTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $manager;

    private Department $dyeing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->dyeing = Department::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->dhaka->id,
            'name' => 'Dyeing',
            'code' => 'DYE',
        ]);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Karim Mia',
            'employee_id' => 'EMP-1001',
            'factory_id' => $this->dhaka->id,
            'department_id' => $this->dyeing->id,
            'specialization' => 'Soft flow dyeing machines',
        ], $overrides);
    }

    public function test_somebody_can_be_put_on_the_roster_with_an_area(): void
    {
        $this->actingAs($this->manager)
            ->post('/app/technicians', $this->payload())
            ->assertRedirect('/app/technicians');

        $technician = Technician::where('employee_id', 'EMP-1001')->firstOrFail();

        $this->assertSame($this->delta->id, $technician->company_id);
        $this->assertSame($this->dyeing->id, $technician->department_id);
        $this->assertSame('ACTIVE', $technician->status);
    }

    /**
     * The record carries no money at all. Technicians are salaried, so an
     * hourly figure here would be one nobody's payroll agrees with.
     */
    public function test_the_roster_holds_no_pay_of_any_kind(): void
    {
        $this->actingAs($this->manager)->post('/app/technicians', $this->payload());

        $technician = Technician::where('employee_id', 'EMP-1001')->firstOrFail();

        foreach (['salary', 'wage', 'hourly_rate', 'labor_grade_id', 'rate'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $technician->getAttributes());
        }

        $this->actingAs($this->manager)
            ->get('/app/technicians')
            ->assertOk()
            ->assertDontSee('rate', escape: false);
    }

    public function test_a_line_needs_its_department_named_too(): void
    {
        $line = ProductionLine::create([
            'company_id' => $this->delta->id,
            'department_id' => $this->dyeing->id,
            'name' => 'Dye line 1',
            'code' => 'DL1',
        ]);

        $this->actingAs($this->manager)
            ->from('/app/technicians/create')
            ->post('/app/technicians', $this->payload([
                'department_id' => null,
                'production_line_id' => $line->id,
            ]))
            ->assertSessionHasErrors('department_id');

        $this->assertSame(0, Technician::where('employee_id', 'EMP-1001')->count());
    }

    public function test_somebody_can_cover_a_whole_factory(): void
    {
        // A small factory has one mechanic for everything, and forcing a
        // department on them would be inventing structure they do not have.
        $this->actingAs($this->manager)
            ->post('/app/technicians', $this->payload(['department_id' => null]))
            ->assertRedirect();

        $this->assertNull(Technician::where('employee_id', 'EMP-1001')->firstOrFail()->department_id);
    }

    public function test_an_area_from_another_company_is_refused(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $theirFactory = TenantFixture::factory($other, 'Their Unit', 'BTU');

        $theirDepartment = Department::create([
            'company_id' => $other->id,
            'factory_id' => $theirFactory->id,
            'name' => 'Their dyeing',
            'code' => 'BTL-DYE',
        ]);

        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($this->manager)
            ->from('/app/technicians/create')
            ->post('/app/technicians', $this->payload(['department_id' => $theirDepartment->id]))
            ->assertSessionHasErrors('department_id');

        $this->actingAs($this->manager)
            ->from('/app/technicians/create')
            ->post('/app/technicians', $this->payload(['factory_id' => $theirFactory->id]))
            ->assertSessionHasErrors('factory_id');

        $this->assertSame(0, Technician::withoutGlobalScopes()->where('employee_id', 'EMP-1001')->count());
    }

    public function test_an_employee_id_cannot_be_used_twice(): void
    {
        $this->actingAs($this->manager)->post('/app/technicians', $this->payload());

        $this->actingAs($this->manager)
            ->from('/app/technicians/create')
            ->post('/app/technicians', $this->payload(['name' => 'Somebody else']))
            ->assertSessionHasErrors('employee_id');

        $this->assertSame(1, Technician::where('employee_id', 'EMP-1001')->count());
    }

    public function test_somebody_is_taken_off_the_roster_rather_than_deleted_once_they_have_worked(): void
    {
        $this->actingAs($this->manager)->post('/app/technicians', $this->payload());

        $technician = Technician::where('employee_id', 'EMP-1001')->firstOrFail();

        $this->actingAs($this->manager)
            ->post('/app/technicians/'.$technician->id.'/toggle')
            ->assertRedirect();

        $this->assertSame('INACTIVE', $technician->fresh()->status);

        // Nothing filed against them yet, so removing the row is still allowed.
        $this->actingAs($this->manager)
            ->delete('/app/technicians/'.$technician->id)
            ->assertRedirect('/app/technicians');

        $this->assertNull(Technician::find($technician->id));
    }

    public function test_the_roster_is_closed_to_people_who_do_not_run_it(): void
    {
        $technicianUser = TenantFixture::user(
            $this->delta, 'TECHNICIAN', 'tech@delta.test', factoryId: $this->dhaka->id,
        );
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($technicianUser)->get('/app/technicians')->assertForbidden();
        $this->actingAs($technicianUser)->post('/app/technicians', $this->payload())->assertForbidden();

        $this->assertSame(0, Technician::where('employee_id', 'EMP-1001')->count());
    }

    public function test_a_factory_scoped_manager_cannot_staff_another_factory(): void
    {
        $gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');

        $scoped = TenantFixture::user(
            $this->delta, 'FACTORY_MANAGER', 'dhaka@delta.test', factoryId: $this->dhaka->id,
        );
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($scoped)
            ->from('/app/technicians/create')
            ->post('/app/technicians', $this->payload(['factory_id' => $gazipur->id, 'department_id' => null]))
            ->assertSessionHasErrors('factory_id');
    }
}
