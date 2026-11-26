<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The unit pick lists (AnnotechERP): the screens that keep a unit as its code
 * offer the units-of-measure master — the platform's units and the company's
 * own — and the API takes a code in any case or a common spelling of it,
 * stores the master's code, and turns away a unit the master does not have.
 */
class UomPickListTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_units_come_from_the_master_and_are_stored_as_its_codes(): void
    {
        // The company's own unit joins the platform's; another company's does not.
        UnitOfMeasure::query()->create(['company_id' => $this->delta->id, 'code' => 'CTN', 'name' => 'Carton', 'active' => true]);
        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        UnitOfMeasure::query()->create(['company_id' => $omega->id, 'code' => 'BALE', 'name' => 'Bale', 'active' => true]);
        $codes = collect($this->as()->getJson('/api/v1/lookups/uoms')->assertOk()->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('PCS') && $codes->contains('KG') && $codes->contains('CTN'));
        $this->assertFalse($codes->contains('BALE'));
        $this->assertSame(['code' => 'PCS', 'name' => 'Pieces'], collect($this->as()->getJson('/api/v1/lookups/uoms')->json('data'))->firstWhere('code', 'PCS'));

        // Spellings people type become the master's code; a unit it lacks is turned away with the reason.
        $this->as()->postJson('/api/v1/erp/material-trims', ['item_code' => 'TR-1', 'name' => 'Main Label', 'category' => 'Label', 'uom' => 'pieces'])->assertCreated()->assertJsonPath('data.uom', 'PCS');
        $this->as()->postJson('/api/v1/erp/material-fabrics', ['item_code' => 'FB-1', 'name' => 'Jersey', 'category' => 'Knit', 'uom' => 'Kgs'])->assertCreated()->assertJsonPath('data.uom', 'KG');
        $this->as()->postJson('/api/v1/erp/material-trims', ['item_code' => 'TR-2', 'name' => 'Carton', 'category' => 'Packing', 'uom' => 'ctn'])->assertCreated()->assertJsonPath('data.uom', 'CTN');
        $this->as()->postJson('/api/v1/erp/material-trims', ['item_code' => 'TR-3', 'name' => 'Bale', 'category' => 'Packing', 'uom' => 'BALE'])
            ->assertUnprocessable()->assertJsonPath('errors.uom.0', 'Pick BALE from the units of measure (Settings → Master Data), or add it there first.');
        $this->as()->postJson('/api/v1/erp/rate-lines', ['rate_group' => 'KNITTING', 'description' => 'S/Jersey knitting', 'uom' => 'yards'])->assertCreated()->assertJsonPath('data.uom', 'YDS');
        $this->as()->postJson('/api/v1/erp/rate-lines', ['rate_group' => 'KNITTING', 'description' => 'Rib knitting'])->assertCreated()->assertJsonPath('data.uom', null);

        // Someone who may open none of the screens that ask for a unit does not get the list.
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->as($technician)->getJson('/api/v1/lookups/uoms')->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
