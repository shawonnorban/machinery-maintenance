<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Setting up a factory and the places machines stand in it (SRS 4, ADR-052).
 *
 * Until these screens existed a new tenant could not register a single
 * machine: an asset names a factory and a location, and both could only be
 * created by a seeder or a spreadsheet import.
 */
class FactoryAndLocationTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    public function test_a_factory_can_be_created_from_the_screen(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/settings/factories', [
                'name' => 'Gazipur Knitting Unit',
                'code' => 'gaz',
                'timezone' => 'Asia/Dhaka',
                'address' => 'Gazipur',
            ])
            ->assertRedirect('/app/settings/factories');

        $factory = Factory::where('name', 'Gazipur Knitting Unit')->firstOrFail();

        // Uppercased, because the code is printed on labels and embedded in
        // work order numbers.
        $this->assertSame('GAZ', $factory->code);
        $this->assertSame('Asia/Dhaka', $factory->timezone);
        $this->assertSame('ACTIVE', $factory->status);
    }

    /**
     * The code is the one field that cannot be corrected later.
     */
    public function test_a_factory_code_is_fixed_after_creation(): void
    {
        $this->actingAs($this->owner)
            ->patch('/app/settings/factories/'.$this->dhaka->id, [
                'name' => 'Dhaka Unit 1 (renamed)',
                'code' => 'XXX',
                'timezone' => 'Asia/Dhaka',
            ])
            ->assertRedirect();

        $factory = $this->dhaka->fresh();

        $this->assertSame('Dhaka Unit 1 (renamed)', $factory->name);
        $this->assertSame('DHK', $factory->code);
    }

    public function test_a_duplicate_factory_code_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->from('/app/settings/factories/create')
            ->post('/app/settings/factories', [
                'name' => 'Another unit',
                'code' => 'DHK',
                'timezone' => 'Asia/Dhaka',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_a_factory_with_machines_is_closed_rather_than_deleted(): void
    {
        WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($this->owner)
            ->from('/app/settings/factories')
            ->delete('/app/settings/factories/'.$this->dhaka->id)
            ->assertSessionHasErrors('code');

        $this->assertNotNull(Factory::find($this->dhaka->id));

        // Closing is the way out: it stops being offered while still owning
        // everything that ever happened in it.
        $this->actingAs($this->owner)
            ->post('/app/settings/factories/'.$this->dhaka->id.'/toggle')
            ->assertRedirect();

        $this->assertSame('INACTIVE', $this->dhaka->fresh()->status);
    }

    public function test_an_empty_factory_registered_by_mistake_can_be_removed(): void
    {
        $mistake = TenantFixture::factory($this->delta, 'Typed twice', 'TYP');
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($this->owner)
            ->delete('/app/settings/factories/'.$mistake->id)
            ->assertRedirect('/app/settings/factories');

        $this->assertNull(Factory::find($mistake->id));
    }

    public function test_a_location_is_created_with_a_scannable_token_and_a_path(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/settings/locations', [
                'factory_id' => $this->dhaka->id,
                'name' => 'Line 3',
                'code' => 'dhk-l3',
                'status' => 'ACTIVE',
            ])
            ->assertRedirect('/app/settings/locations');

        $location = AssetLocation::where('code', 'DHK-L3')->firstOrFail();

        // Both were dangling capabilities before this screen existed: the QR
        // generator had a forLocation() nothing called, and full_path was read
        // on six screens and written by nothing.
        $this->assertSame(12, strlen((string) $location->qr_code));
        $this->assertStringContainsString('Dhaka Unit 1', (string) $location->full_path);
        $this->assertStringContainsString('Line 3', (string) $location->full_path);
    }

    public function test_a_location_cannot_be_put_in_another_companys_factory(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $theirFactory = TenantFixture::factory($other, 'Their Unit', 'BTU');

        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($this->owner)
            ->from('/app/settings/locations/create')
            ->post('/app/settings/locations', [
                'factory_id' => $theirFactory->id,
                'name' => 'Borrowed line',
                'code' => 'BORROWED',
            ])
            ->assertSessionHasErrors('factory_id');

        $this->assertSame(0, AssetLocation::withoutGlobalScopes()->where('code', 'BORROWED')->count());
    }

    public function test_a_location_with_machines_in_it_is_closed_rather_than_deleted(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        TenantFixture::actingAsTenant($this->delta);

        $location = AssetLocation::findOrFail($asset->asset_location_id);

        $this->actingAs($this->owner)
            ->from('/app/settings/locations')
            ->delete('/app/settings/locations/'.$location->id)
            ->assertSessionHasErrors('code');

        $this->assertNotNull(AssetLocation::find($location->id));

        $this->actingAs($this->owner)
            ->post('/app/settings/locations/'.$location->id.'/toggle')
            ->assertRedirect();

        $this->assertSame('INACTIVE', $location->fresh()->status);
    }

    public function test_an_unused_location_can_be_removed(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/settings/locations', [
                'factory_id' => $this->dhaka->id,
                'name' => 'Typed twice',
                'code' => 'DHK-TYPO',
            ])
            ->assertRedirect();

        $location = AssetLocation::where('code', 'DHK-TYPO')->firstOrFail();

        $this->actingAs($this->owner)
            ->delete('/app/settings/locations/'.$location->id)
            ->assertRedirect('/app/settings/locations');

        $this->assertNull(AssetLocation::find($location->id));
    }

    public function test_renaming_a_location_rebuilds_the_path_shown_everywhere(): void
    {
        $this->actingAs($this->owner)->post('/app/settings/locations', [
            'factory_id' => $this->dhaka->id,
            'name' => 'Line 3',
            'code' => 'DHK-L3',
        ]);

        $location = AssetLocation::where('code', 'DHK-L3')->firstOrFail();

        $this->actingAs($this->owner)
            ->patch('/app/settings/locations/'.$location->id, [
                'factory_id' => $this->dhaka->id,
                'name' => 'Line 3 (rebuilt)',
                'code' => 'DHK-L3',
            ])
            ->assertRedirect();

        // Denormalised, so it has to be rebuilt on every save or the asset
        // screens keep showing the old name.
        $this->assertStringContainsString('Line 3 (rebuilt)', (string) $location->fresh()->full_path);
    }

    public function test_the_screens_are_closed_to_roles_that_do_not_configure(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($technician)->get('/app/settings/factories')->assertForbidden();
        $this->actingAs($technician)->get('/app/settings/locations')->assertForbidden();
        $this->actingAs($technician)
            ->post('/app/settings/factories', ['name' => 'X', 'code' => 'X', 'timezone' => 'UTC'])
            ->assertForbidden();
    }

    public function test_the_lists_render_and_the_sidebar_links_to_them(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($this->owner)
            ->get('/app/settings/factories')
            ->assertOk()
            ->assertSee('Dhaka Unit 1');

        $this->actingAs($this->owner)
            ->get('/app/settings/locations')
            ->assertOk()
            ->assertSee(AssetLocation::findOrFail($asset->asset_location_id)->code);

        $this->actingAs($this->owner)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertSee(route('app.settings.factories'), escape: false)
            ->assertSee(route('app.settings.locations'), escape: false);
    }

    public function test_a_factory_scoped_user_is_only_offered_their_own_factory(): void
    {
        $gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');

        $scoped = TenantFixture::user(
            $this->delta, 'FACTORY_MANAGER', 'dhaka@delta.test', factoryId: $this->dhaka->id,
        );

        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($scoped)
            ->get('/app/settings/locations/create')
            ->assertOk()
            ->assertSee('Dhaka Unit 1')
            // Listing the rest would leak the shape of the estate.
            ->assertDontSee('Gazipur Unit 2');

        $this->actingAs($scoped)
            ->from('/app/settings/locations/create')
            ->post('/app/settings/locations', [
                'factory_id' => $gazipur->id,
                'name' => 'Out of reach',
                'code' => 'GAZ-X',
            ])
            ->assertSessionHasErrors('factory_id');

        $this->assertSame(0, AssetLocation::where('code', 'GAZ-X')->count());
    }
}
