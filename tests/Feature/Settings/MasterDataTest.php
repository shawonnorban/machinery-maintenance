<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Asset\Models\Manufacturer;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Breakdown\Models\DowntimeReasonCode;
use App\Modules\Identity\Models\User;
use App\Modules\Metering\Models\MeterType;
use App\Modules\Settings\MasterData\MasterDataRegistry;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Reference data, managed rather than only imported (SRS 6, Seed Catalog 1).
 *
 * The lists here — asset types, failure codes, downtime reasons — are the
 * vocabulary every screen and every report is written in. Two properties
 * matter more than the CRUD: a platform row is shared with every tenant and so
 * belongs to none of them, and a code has to mean one thing, because imports
 * and cost posting resolve master data by code.
 */
class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
    }

    private function user(string $role = 'FACTORY_MANAGER', string $email = 'fm@delta.test'): User
    {
        $user = TenantFixture::user($this->delta, $role, $email);
        TenantFixture::actingAsTenant($this->delta);

        return $user;
    }

    public function test_every_registered_list_has_a_working_screen(): void
    {
        $manager = $this->user();

        $this->actingAs($manager)->get('/app/settings/master-data')->assertOk();

        // Each of the dozen lists shares one controller, so a type whose model
        // or columns are wrong shows up here rather than in production.
        foreach (app(MasterDataRegistry::class)->all() as $key => $type) {
            $this->actingAs($manager)
                ->get('/app/settings/master-data/'.$key)
                ->assertOk()
                ->assertSee($type->title());
        }
    }

    public function test_a_company_can_add_its_own_entry(): void
    {
        $manager = $this->user();

        $this->actingAs($manager)
            ->post('/app/settings/master-data/manufacturers', [
                'name' => 'Sunstar Machinery',
                'code' => 'sunstar',
                'country' => 'KR',
                'active' => '1',
            ])
            ->assertRedirect('/app/settings/master-data/manufacturers');

        $row = Manufacturer::where('name', 'Sunstar Machinery')->firstOrFail();

        // Owned by the company that created it, and the code normalised, so the
        // same manufacturer cannot arrive as "sunstar" one day and "SUNSTAR"
        // the next.
        $this->assertSame($this->delta->id, $row->company_id);
        $this->assertSame('SUNSTAR', $row->code);
    }

    /**
     * The rule the whole ownership model rests on.
     */
    public function test_a_platform_entry_cannot_be_edited_or_deactivated(): void
    {
        $manager = $this->user();

        $platform = AssetType::whereNull('company_id')->firstOrFail();

        $this->actingAs($manager)
            ->from('/app/settings/master-data/asset-types')
            ->put('/app/settings/master-data/asset-types/'.$platform->id, [
                'name' => 'Renamed by one tenant',
                'code' => $platform->code,
                'default_criticality' => 'LOW',
                'active' => '1',
            ])
            ->assertSessionHasErrors('code');

        $this->actingAs($manager)
            ->from('/app/settings/master-data/asset-types')
            ->post('/app/settings/master-data/asset-types/'.$platform->id.'/toggle')
            ->assertSessionHasErrors('code');

        // A shared row renamed by one company would be renamed for strangers.
        $this->assertSame($platform->name, $platform->fresh()->name);
        $this->assertTrue($platform->fresh()->active);
    }

    /**
     * The only thing a company may do with a shared row.
     *
     * Without it the seeded list is read-only furniture: a factory that words
     * "Sewing machine" differently would have to retype the row rather than
     * start from it.
     */
    public function test_a_platform_entry_can_be_copied_into_the_companys_own(): void
    {
        $manager = $this->user();

        $platform = AssetType::whereNull('company_id')->firstOrFail();

        $this->actingAs($manager)
            ->get('/app/settings/master-data/asset-types?copy='.$platform->id)
            ->assertOk()
            // Everything but the code, which has to be new.
            ->assertSee($platform->name);

        $this->actingAs($manager)
            ->post('/app/settings/master-data/asset-types', [
                'name' => $platform->name,
                'code' => 'DAL_'.$platform->code,
                'default_criticality' => $platform->default_criticality,
                'active' => '1',
            ])
            ->assertRedirect();

        $copy = AssetType::where('code', 'DAL_'.$platform->code)->firstOrFail();

        $this->assertSame($this->delta->id, $copy->company_id);
        $this->assertSame($platform->name, $copy->name);
    }

    /**
     * Usage-based maintenance has nothing to hang off until these exist
     * (Seed Catalog 7).
     */
    public function test_the_platform_seeds_the_meter_types_a_factory_counts_with(): void
    {
        $manager = $this->user();

        $this->actingAs($manager)
            ->get('/app/settings/master-data/meter-types')
            ->assertOk()
            ->assertSee('RUNNING_HOURS');

        $this->assertGreaterThanOrEqual(
            8,
            MeterType::availableTo($this->delta->id)->count(),
        );
    }

    public function test_a_code_cannot_shadow_one_the_company_already_sees(): void
    {
        $manager = $this->user();

        $platform = AssetType::whereNull('company_id')->firstOrFail();

        $this->actingAs($manager)
            ->from('/app/settings/master-data/asset-types')
            ->post('/app/settings/master-data/asset-types', [
                'name' => 'Our own sewing',
                'code' => $platform->code,
                'default_criticality' => 'HIGH',
                'active' => '1',
            ])
            ->assertSessionHasErrors('code');

        // Imports and cost posting look master data up by code and take the
        // first match. Two rows with one code makes which one they get depend
        // on row order.
        $this->assertSame(
            1,
            AssetType::availableTo($this->delta->id)->where('code', $platform->code)->count(),
        );
    }

    public function test_a_reference_must_point_at_something_the_company_can_see(): void
    {
        $manager = $this->user();

        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        TenantFixture::actingAsTenant($other);

        $theirType = AssetType::create([
            'company_id' => $other->id,
            'name' => 'Their private type',
            'code' => 'BTL_ONLY',
            'default_criticality' => 'MEDIUM',
            'active' => true,
        ]);

        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($manager)
            ->from('/app/settings/master-data/asset-categories')
            ->post('/app/settings/master-data/asset-categories', [
                'asset_type_id' => $theirType->id,
                'name' => 'Borrowed',
                'code' => 'BORROWED',
                'active' => '1',
            ])
            ->assertSessionHasErrors('asset_type_id');

        // A ulid copied off another tenant's screen must not link the two
        // companies' data together.
        $this->assertSame(0, AssetCategory::where('code', 'BORROWED')->count());
    }

    public function test_an_entry_in_use_is_deactivated_rather_than_deleted(): void
    {
        $manager = $this->user();

        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $type = AssetType::findOrFail($asset->asset_type_id);

        if ($type->company_id === null) {
            // The fixture uses a platform type; give the company its own copy
            // to delete so the test is about usage, not ownership.
            $type = AssetType::create([
                'company_id' => $this->delta->id,
                'name' => 'Company type',
                'code' => 'DAL_TYPE',
                'default_criticality' => 'MEDIUM',
                'active' => true,
            ]);

            $asset->forceFill(['asset_type_id' => $type->id])->save();
        }

        $this->actingAs($manager)
            ->from('/app/settings/master-data/asset-types')
            ->delete('/app/settings/master-data/asset-types/'.$type->id)
            ->assertSessionHasErrors('code');

        $this->assertNotNull($type->fresh());

        // Deactivating is the way out: it leaves every machine already filed
        // under it reading correctly.
        $this->actingAs($manager)
            ->post('/app/settings/master-data/asset-types/'.$type->id.'/toggle')
            ->assertRedirect();

        $this->assertFalse($type->fresh()->active);
    }

    /**
     * A route nothing links to is a route nobody uses.
     */
    public function test_the_screen_offers_delete_and_a_way_back(): void
    {
        $manager = $this->user();

        $own = AssetType::create([
            'company_id' => $this->delta->id,
            'name' => 'Ours',
            'code' => 'DAL_OWN',
            'default_criticality' => 'LOW',
            'active' => true,
        ]);

        $this->actingAs($manager)
            ->get('/app/settings/master-data/asset-types')
            ->assertOk()
            ->assertSee(route('app.settings.master-data.destroy', ['asset-types', $own->id]), escape: false)
            ->assertSee(route('app.settings.master-data'), escape: false);
    }

    public function test_an_unused_company_entry_can_be_removed(): void
    {
        $manager = $this->user();

        $type = AssetType::create([
            'company_id' => $this->delta->id,
            'name' => 'Typed in by mistake',
            'code' => 'MISTAKE',
            'default_criticality' => 'LOW',
            'active' => true,
        ]);

        $this->actingAs($manager)
            ->delete('/app/settings/master-data/asset-types/'.$type->id)
            ->assertRedirect();

        $this->assertNull(AssetType::find($type->id));
    }

    public function test_another_tenants_entry_is_not_reachable(): void
    {
        $manager = $this->user();

        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        TenantFixture::actingAsTenant($other);

        $theirs = AssetType::create([
            'company_id' => $other->id,
            'name' => 'Theirs',
            'code' => 'BTL_ONLY',
            'default_criticality' => 'MEDIUM',
            'active' => true,
        ]);

        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($manager)
            ->put('/app/settings/master-data/asset-types/'.$theirs->id, [
                'name' => 'Renamed',
                'code' => 'BTL_ONLY',
                'default_criticality' => 'LOW',
                'active' => '1',
            ])
            ->assertNotFound();

        $this->assertSame('Theirs', $theirs->fresh()->name);
    }

    public function test_the_screens_are_closed_to_roles_that_do_not_configure(): void
    {
        $technician = $this->user('TECHNICIAN', 'tech@delta.test');

        $this->actingAs($technician)->get('/app/settings/master-data')->assertForbidden();
        $this->actingAs($technician)->get('/app/settings/master-data/asset-types')->assertForbidden();
        $this->actingAs($technician)
            ->post('/app/settings/master-data/asset-types', [
                'name' => 'X', 'code' => 'X', 'default_criticality' => 'LOW', 'active' => '1',
            ])
            ->assertForbidden();
    }

    /**
     * Changing a downtime reason changes what availability meant last quarter,
     * without a single breakdown record being touched.
     */
    public function test_a_change_to_a_reference_list_is_audited(): void
    {
        $manager = $this->user();

        $reason = DowntimeReasonCode::create([
            'company_id' => $this->delta->id,
            'code' => 'TEA_BREAK',
            'name' => 'Tea break',
            'downtime_class' => 'NON_OPERATING',
            'counts_against_availability' => false,
            'active' => true,
        ]);

        $this->actingAs($manager)
            ->put('/app/settings/master-data/downtime-reason-codes/'.$reason->id, [
                'name' => 'Tea break',
                'code' => 'TEA_BREAK',
                'downtime_class' => 'UNPLANNED',
                'counts_against_availability' => '1',
                'active' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($reason->fresh()->counts_against_availability);

        $this->assertSame(
            1,
            AuditLog::forCompany($this->delta->id)
                ->where('entity_type', 'downtime_reason_codes')
                ->where('entity_id', $reason->id)
                ->where('action', 'UPDATED')
                ->count(),
        );
    }
}
