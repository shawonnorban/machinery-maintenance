<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Asset\Actions\TransferAsset;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetModel;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Asset\Models\Manufacturer;
use App\Modules\Identity\Models\Team;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\SparePartCompatibility;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\TechnicianSkill;
use App\Shared\Files\Models\FileAttachment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Seven capabilities the permission list promised and no screen delivered.
 *
 * Each was the same shape of gap: a permission granted to a role, a table in
 * the schema, and nothing a person could click. Grouped into one file because
 * they share nothing but that.
 */
class SmallGapsTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Factory $gazipur;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        $this->gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    // -- 1. Receiving a transferred machine ---------------------------------

    /**
     * The far end confirms, not the end that sent it.
     */
    public function test_only_the_receiving_factory_confirms_a_machine_arrived(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $destination = AssetLocation::firstOrCreate(
            ['factory_id' => $this->gazipur->id, 'code' => 'GAZ-L1'],
            ['name' => 'Line 1'],
        );

        $sender = TenantFixture::user(
            $this->delta, 'FACTORY_ADMIN', 'dhaka@delta.test', factoryId: $this->dhaka->id,
        );
        TenantFixture::actingAsTenant($this->delta);

        $transfers = app(TransferAsset::class);

        $transfer = $transfers->request(
            $asset, $destination->id, 'Gazipur is short a lockstitch machine', $sender->id,
        );

        $transfers->approve($transfer, $this->owner->id);

        $this->flushSession();

        // A sending manager ticking this off marks a machine as installed
        // somewhere nobody has laid eyes on it.
        $this->actingAs($sender)
            ->post('/app/transfers/'.$transfer->id.'/receive')
            ->assertForbidden();

        $this->assertSame('APPROVED', $transfer->fresh()->status);
    }

    public function test_the_receiving_factory_confirms_and_the_machine_moves(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $destination = AssetLocation::firstOrCreate(
            ['factory_id' => $this->gazipur->id, 'code' => 'GAZ-L1'],
            ['name' => 'Line 1'],
        );

        $receiver = TenantFixture::user(
            $this->delta, 'FACTORY_ADMIN', 'gazipur@delta.test', factoryId: $this->gazipur->id,
        );
        TenantFixture::actingAsTenant($this->delta);

        $transfers = app(TransferAsset::class);
        $transfer = $transfers->request($asset, $destination->id, 'Short a machine', $this->owner->id);
        $transfers->approve($transfer, $receiver->id);

        $this->flushSession();

        $this->actingAs($receiver)
            ->post('/app/transfers/'.$transfer->id.'/receive')
            ->assertRedirect();

        // Receiving is the only point at which the machine actually moves.
        $asset->refresh();

        $this->assertSame($this->gazipur->id, $asset->current_factory_id);
        $this->assertSame($destination->id, $asset->asset_location_id);
        $this->assertSame('RECEIVED', $transfer->fresh()->status);
    }

    // -- 2. A machine's papers ----------------------------------------------

    public function test_a_manual_can_be_attached_to_a_machine_and_removed(): void
    {
        Storage::fake('local');

        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $this->actingAs($this->owner)
            ->post('/app/assets/'.$asset->id.'/documents', [
                'file' => UploadedFile::fake()->create('soft-flow-manual.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = FileAttachment::where('attachable_type', 'asset')
            ->where('attachable_id', $asset->id)
            ->firstOrFail();

        $this->assertSame('soft-flow-manual.pdf', $document->original_name);

        // It shows on the machine's own screen, which is where somebody
        // standing at the machine will look for it.
        $this->actingAs($this->owner)
            ->get('/app/assets/'.$asset->id)
            ->assertOk()
            ->assertSee('soft-flow-manual.pdf');

        $this->actingAs($this->owner)
            ->delete('/app/assets/'.$asset->id.'/documents/'.$document->id)
            ->assertRedirect();

        $this->assertNull(FileAttachment::find($document->id));
    }

    public function test_a_technician_can_read_a_manual_without_work_order_permission(): void
    {
        Storage::fake('local');

        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $this->actingAs($this->owner)->post('/app/assets/'.$asset->id.'/documents', [
            'file' => UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf'),
        ]);

        $document = FileAttachment::where('attachable_type', 'asset')->firstOrFail();

        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->flushSession();

        // A machine's manual is not evidence from somebody's work order, and
        // asking for the work order permission would deny it to the people who
        // need it most.
        $this->actingAs($technician)
            ->get('/app/attachments/'.$document->id)
            ->assertOk();
    }

    // -- 3. Removing a maintenance plan -------------------------------------

    public function test_a_plan_that_has_generated_work_is_deactivated_rather_than_deleted(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $plan = MaintenancePlan::create([
            'company_id' => $this->delta->id,
            'asset_id' => $asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'name' => 'Monthly service',
            'trigger_type' => 'TIME',
            'schedule_mode' => 'ROLLING',
            'start_date' => now()->toDateString(),
            'active' => true,
        ]);

        $plan->schedules()->create([
            'company_id' => $this->delta->id,
            'asset_id' => $asset->id,
            'due_at' => now()->addWeek(),
            'status' => 'PLANNED',
        ]);

        $this->actingAs($this->owner)
            ->from('/app/maintenance/plans')
            ->delete('/app/maintenance/plans/'.$plan->id)
            ->assertSessionHasErrors('plan');

        $this->assertNotNull(MaintenancePlan::find($plan->id));
    }

    public function test_a_plan_with_no_history_can_be_removed(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $plan = MaintenancePlan::create([
            'company_id' => $this->delta->id,
            'asset_id' => $asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'name' => 'Typed twice',
            'trigger_type' => 'TIME',
            'schedule_mode' => 'ROLLING',
            'start_date' => now()->toDateString(),
            'active' => false,
        ]);

        $this->actingAs($this->owner)
            ->delete('/app/maintenance/plans/'.$plan->id)
            ->assertRedirect('/app/maintenance/plans');

        $this->assertNull(MaintenancePlan::find($plan->id));
    }

    // -- 4. Issuing outside a work order ------------------------------------

    public function test_consumables_can_leave_the_store_without_a_work_order(): void
    {
        $bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $part = InventoryFixture::part($this->delta);

        app(InventoryLedger::class)->post($part, $bin, 'RECEIPT', '20', '50');

        $this->actingAs($this->owner)
            ->post('/app/inventory/issue', [
                'spare_part_id' => $part->id,
                'bin_id' => $bin->id,
                'transaction_type' => 'ISSUE',
                'quantity' => '5',
                'notes' => 'Karim, dye house, monthly cleaning',
            ])
            ->assertRedirect();

        $movement = InventoryTransaction::where('transaction_type', 'ISSUE')->firstOrFail();

        // Recorded in the ledger and charged to no machine: there is no machine.
        $this->assertNull($movement->work_order_id);
        $this->assertSame('15.0000', $part->fresh()->totalOnHand());
    }

    public function test_stock_cannot_leave_the_store_without_saying_why(): void
    {
        $bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $part = InventoryFixture::part($this->delta);

        app(InventoryLedger::class)->post($part, $bin, 'RECEIPT', '20', '50');

        $this->actingAs($this->owner)
            ->from('/app/inventory/issue')
            ->post('/app/inventory/issue', [
                'spare_part_id' => $part->id,
                'bin_id' => $bin->id,
                'transaction_type' => 'ISSUE',
                'quantity' => '5',
            ])
            ->assertSessionHasErrors('notes');

        // Stock that moves with no work order and no explanation is
        // indistinguishable from loss.
        $this->assertSame('20.0000', $part->fresh()->totalOnHand());
    }

    // -- 5. Teams -----------------------------------------------------------

    public function test_a_team_can_be_created_and_is_kept_once_work_names_it(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/teams', [
                'name' => 'Dye house crew',
                'code' => 'dye-crew',
                'factory_id' => $this->dhaka->id,
                'specialization' => 'Dyeing machines',
            ])
            ->assertRedirect();

        $team = Team::where('code', 'DYE-CREW')->firstOrFail();

        $this->assertSame($this->delta->id, $team->company_id);
        $this->assertSame('ACTIVE', $team->status);

        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        MaintenancePlan::create([
            'company_id' => $this->delta->id,
            'asset_id' => $asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'name' => 'Monthly service',
            'trigger_type' => 'TIME',
            'schedule_mode' => 'ROLLING',
            'start_date' => now()->toDateString(),
            'assigned_team_id' => $team->id,
            'active' => true,
        ]);

        $this->actingAs($this->owner)
            ->from('/app/teams')
            ->delete('/app/teams/'.$team->id)
            ->assertSessionHasErrors('name');

        // A job still has to be able to say who it went to.
        $this->assertNotNull(Team::find($team->id));
    }

    // -- 6. Fits and substitutes --------------------------------------------

    public function test_a_part_can_record_what_it_fits_and_what_stands_in_for_it(): void
    {
        $part = InventoryFixture::part($this->delta);
        $alternative = InventoryFixture::part($this->delta, 'JK-ALT-001', 'Rotary hook, generic');

        $model = AssetModel::whereNull('company_id')->first()
            ?? AssetModel::create([
                'company_id' => $this->delta->id,
                'manufacturer_id' => Manufacturer::whereNull('company_id')->firstOrFail()->id,
                'asset_type_id' => AssetType::whereNull('company_id')->firstOrFail()->id,
                'model' => 'DDL-9000C',
                'code' => 'DDL9000C',
                'active' => true,
            ]);

        $this->actingAs($this->owner)->post('/app/inventory/parts/'.$part->id.'/compatibility', [
            'compatibility_type' => 'FITS',
            'asset_model_id' => $model->id,
        ])->assertRedirect();

        $this->actingAs($this->owner)->post('/app/inventory/parts/'.$alternative->id.'/compatibility', [
            'compatibility_type' => 'SUBSTITUTE',
            'substitute_for_part_id' => $part->id,
        ])->assertRedirect();

        $this->assertSame(1, SparePartCompatibility::where('spare_part_id', $part->id)
            ->where('compatibility_type', 'FITS')->count());

        // Recorded so a failure analysis can later say the machine ran on the
        // second-best part.
        $this->assertSame(1, SparePartCompatibility::where('spare_part_id', $alternative->id)
            ->where('substitute_for_part_id', $part->id)->count());
    }

    public function test_a_part_cannot_stand_in_for_itself(): void
    {
        $part = InventoryFixture::part($this->delta);

        $this->actingAs($this->owner)
            ->from('/app/inventory/parts/'.$part->id)
            ->post('/app/inventory/parts/'.$part->id.'/compatibility', [
                'compatibility_type' => 'SUBSTITUTE',
                'substitute_for_part_id' => $part->id,
            ])
            ->assertSessionHasErrors('substitute_for_part_id');

        $this->assertSame(0, SparePartCompatibility::count());
    }

    // -- 7. What a technician is trained on ---------------------------------

    public function test_skills_can_be_recorded_against_a_technician(): void
    {
        $technician = WorkOrderFixture::technician($this->delta, $this->dhaka, 'Karim Mia', 'EMP-1001');
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($this->owner)
            ->post('/app/technicians/'.$technician->id.'/skills', [
                'skill_name' => 'Soft flow dyeing',
                'proficiency' => 'EXPERT',
            ])
            ->assertRedirect();

        $skill = TechnicianSkill::where('technician_id', $technician->id)->firstOrFail();

        $this->assertSame('Soft flow dyeing', $skill->skill_name);

        // The same skill twice would say nothing new and read as a duplicate.
        $this->actingAs($this->owner)
            ->from('/app/technicians/'.$technician->id.'/edit')
            ->post('/app/technicians/'.$technician->id.'/skills', [
                'skill_name' => 'Soft flow dyeing',
                'proficiency' => 'BASIC',
            ])
            ->assertSessionHasErrors('skill_name');

        $this->actingAs($this->owner)
            ->delete('/app/technicians/'.$technician->id.'/skills/'.$skill->id)
            ->assertRedirect();

        $this->assertSame(0, TechnicianSkill::where('technician_id', $technician->id)->count());
    }

    public function test_the_new_screens_render(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        InventoryFixture::bin($this->delta, $this->dhaka);
        $part = InventoryFixture::part($this->delta);

        foreach ([
            '/app/teams',
            '/app/inventory/issue',
            '/app/inventory/parts/'.$part->id,
            '/app/assets/'.$asset->id,
        ] as $url) {
            $this->actingAs($this->owner)->get($url)->assertOk();
        }
    }
}
