<?php

declare(strict_types=1);

namespace Tests\Feature\Vendor;

use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Actions\RecordWarranty;
use App\Modules\Vendor\Models\ServiceContract;
use App\Modules\Vendor\Models\Vendor;
use App\Modules\Vendor\Models\Warranty;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The vendor screens and the expiry alerts over HTTP (SRS 26).
 */
class VendorScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
    }

    private function user(string $role, string $email): User
    {
        $user = TenantFixture::user($this->delta, $role, $email);
        TenantFixture::actingAsTenant($this->delta);

        return $user;
    }

    private function vendor(): Vendor
    {
        return Vendor::create([
            'name' => 'Juki Bangladesh Ltd',
            'code' => 'JUKI-BD',
            'vendor_type' => 'BOTH',
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Vendor records belong to the people who buy from them.
     *
     * A maintenance manager can see vendors but not create them: the supplier
     * list is purchasing's, and letting two departments both maintain it is how
     * a factory ends up with the same vendor entered three times.
     */
    public function test_the_store_manager_can_create_a_vendor(): void
    {
        $manager = $this->user('STORE_MANAGER', 'sm@delta.test');

        $this->actingAs($manager)
            ->post('/app/vendors', [
                'name' => 'Brother Service BD',
                'code' => 'BROTHER-BD',
                'vendor_type' => 'SERVICE',
                'status' => 'ACTIVE',
                'email' => 'service@brother.test',
            ])
            ->assertRedirect();

        $this->assertSame('SERVICE', Vendor::where('code', 'BROTHER-BD')->firstOrFail()->vendor_type);
    }

    public function test_a_technician_cannot_reach_the_vendor_screens(): void
    {
        $technician = $this->user('TECHNICIAN', 'tech@delta.test');

        $this->actingAs($technician)->get('/app/vendors')->assertForbidden();
        $this->actingAs($technician)->get('/app/service-contracts')->assertForbidden();
    }

    public function test_a_technician_can_still_see_that_a_machine_is_covered(): void
    {
        $vendor = $this->vendor();

        app(RecordWarranty::class)->handle([
            'asset_id' => $this->asset->id,
            'vendor_id' => $vendor->id,
            'warranty_type' => 'MANUFACTURER',
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ], 'user-a');

        $technician = $this->user('TECHNICIAN', 'tech2@delta.test');

        // The one thing a technician at a broken machine needs from this module.
        // Gating it behind a management permission is how a factory pays twice
        // for the same fault.
        $this->actingAs($technician)
            ->get(route('app.assets.show', $this->asset))
            ->assertOk()
            ->assertSee('Juki Bangladesh Ltd');

        $this->actingAs($technician)->get('/app/warranties')->assertOk();
    }

    public function test_archiving_a_vendor_keeps_it_resolvable_on_old_records(): void
    {
        $manager = $this->user('STORE_MANAGER', 'sm2@delta.test');
        $vendor = $this->vendor();

        app(RecordWarranty::class)->handle([
            'asset_id' => $this->asset->id,
            'vendor_id' => $vendor->id,
            'warranty_type' => 'MANUFACTURER',
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ], 'user-a');

        $this->actingAs($manager)
            ->delete(route('app.vendors.archive', $vendor))
            ->assertRedirect(route('app.vendors.index'));

        $this->assertSoftDeleted('vendors', ['id' => $vendor->id]);

        // The warranty still names them, which is the point of archiving rather
        // than deleting (ADR-057).
        $warranty = Warranty::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($vendor->id, $warranty->vendor_id);
        $this->assertSame('Juki Bangladesh Ltd', Vendor::withTrashed()->find($vendor->id)->name);
    }

    public function test_the_expiry_command_warns_before_cover_runs_out(): void
    {
        CarbonImmutable::setTestNow('2026-06-15 06:30:00');

        // Warranty alerts reach whoever can act on them — the holder of
        // vendor.warranty.manage, not maintenance planning generally.
        $manager = $this->user('FACTORY_MANAGER', 'fm2@delta.test');
        $vendor = $this->vendor();

        app(RecordWarranty::class)->handle([
            'asset_id' => $this->asset->id,
            'vendor_id' => $vendor->id,
            'warranty_type' => 'MANUFACTURER',
            'start_date' => '2025-01-01',
            // Exactly thirty days out, which is one of the thresholds.
            'end_date' => '2026-07-15',
        ], 'user-a');

        $this->artisan('vendor:coverage-alerts')->assertSuccessful();

        // The command clears tenant context when it finishes, deliberately:
        // nothing after it should inherit the last company it touched.
        TenantFixture::actingAsTenant($this->delta);

        $notification = Notification::where('event_type', 'WARRANTY_EXPIRY')
            ->where('user_id', $manager->id)
            ->first();

        $this->assertNotNull($notification, 'The manager was not warned about expiring cover.');
        $this->assertStringContainsString('Juki Bangladesh Ltd', $notification->body);

        CarbonImmutable::setTestNow();
    }

    public function test_the_expiry_command_does_not_repeat_itself_daily(): void
    {
        CarbonImmutable::setTestNow('2026-06-15 06:30:00');

        $this->user('FACTORY_MANAGER', 'fm3@delta.test');
        $vendor = $this->vendor();

        app(RecordWarranty::class)->handle([
            'asset_id' => $this->asset->id,
            'vendor_id' => $vendor->id,
            'warranty_type' => 'MANUFACTURER',
            'start_date' => '2025-01-01',
            'end_date' => '2026-07-15',
        ], 'user-a');

        $this->artisan('vendor:coverage-alerts')->assertSuccessful();

        // A day later the warranty is 29 days out, which is not a threshold. A
        // message every morning for sixty days is a message nobody reads by day
        // three.
        CarbonImmutable::setTestNow('2026-06-16 06:30:00');
        $this->artisan('vendor:coverage-alerts')->assertSuccessful();

        TenantFixture::actingAsTenant($this->delta);

        $this->assertSame(1, Notification::where('event_type', 'WARRANTY_EXPIRY')->count());

        CarbonImmutable::setTestNow();
    }

    public function test_the_command_expires_what_has_lapsed(): void
    {
        CarbonImmutable::setTestNow('2026-06-15 06:30:00');

        $vendor = $this->vendor();

        $warranty = app(RecordWarranty::class)->handle([
            'asset_id' => $this->asset->id,
            'vendor_id' => $vendor->id,
            'warranty_type' => 'MANUFACTURER',
            'start_date' => '2025-01-01',
            'end_date' => '2026-12-31',
        ], 'user-a');

        CarbonImmutable::setTestNow('2027-01-05 06:30:00');

        $this->artisan('vendor:coverage-alerts')->assertSuccessful();

        // Anything still reading ACTIVE after its end date would show as cover
        // on the screen that decides whether to pay for a repair.
        $this->assertSame('EXPIRED', $warranty->fresh()->status);

        CarbonImmutable::setTestNow();
    }

    public function test_the_contract_screens_render_and_scope_a_contract(): void
    {
        // Contract value is commercial, so signing one sits with the person who
        // signs off high-cost work rather than with maintenance planning.
        $manager = $this->user('FACTORY_MANAGER', 'fm@delta.test');
        $vendor = $this->vendor();

        $this->actingAs($manager)
            ->post('/app/service-contracts', [
                'vendor_id' => $vendor->id,
                'factory_id' => $this->dhaka->id,
                'contract_type' => 'AMC',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
                'value' => '250000',
            ])
            ->assertRedirect();

        $contract = ServiceContract::firstOrFail();

        $this->assertStringStartsWith('AMC-', $contract->contract_number);

        $this->actingAs($manager)
            ->get(route('app.service-contracts.show', $contract))
            ->assertOk()
            ->assertSee($contract->contract_number);
    }

    public function test_the_vendor_screens_render_in_bengali(): void
    {
        $manager = $this->user('MAINTENANCE_MANAGER', 'mm5@delta.test');
        $manager->update(['locale' => 'bn']);

        $this->vendor();

        $this->actingAs($manager)
            ->get('/app/vendors')
            ->assertOk()
            ->assertSee(__('vendor.vendors', locale: 'bn'), false);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/app/vendors')->assertRedirect('/login');
    }
}
