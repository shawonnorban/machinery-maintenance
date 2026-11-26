<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Merchandise\Models\FabricOpeningBalance;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Trims & Accessories → the six closings (AnnotechERP 06/06): Month Close
 * takes Jul/2026 or 2026-07; a month with documents still open is refused,
 * the check and its exceptions recorded for Show Exception; with none it
 * closes, by whom and when, once; afterwards the month's documents of that
 * kind can no longer be saved, moved on or removed.
 */
class MerchandiseStockClosingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Finished Fabric Store', 'code' => 'ST-FAB', 'active' => true])->id;
    }

    public function test_month_close_is_refused_while_documents_are_open_then_closes_and_guards_the_month(): void
    {
        $draft = $this->pfob('PFOB-2026070001', '2026-07-10', 'SUBMITTED');
        $this->pfob('PFOB-2026080001', '2026-08-02', 'DRAFT');

        $this->as()->postJson('/api/v1/merchandise/closings/purchased-fabric', [])->assertUnprocessable()->assertJsonValidationErrors(['period' => 'Period is required.']);
        $this->as()->postJson('/api/v1/merchandise/closings/purchased-fabric', ['period' => 'July 2026'])->assertUnprocessable()
            ->assertJsonValidationErrors(['period' => 'The month being closed, as Jul/2026 or 2026-07.']);
        // July still has a submitted opening balance: refused, the check kept.
        $this->as()->postJson('/api/v1/merchandise/closings/purchased-fabric', ['period' => 'Jul/2026', 'remarks' => 'First try'])->assertUnprocessable()
            ->assertJsonValidationErrors(['period' => 'Jul/2026 cannot close: 1 document is still open — see Show Exception.']);
        $this->assertEquals([['2026-07', 'Jul/2026', 'OPEN', 1, null, 'First try']], array_map(fn ($r) => [$r['period'], $r['label'], $r['status'], $r['exceptions'], $r['closed_by'], $r['remarks']], $this->as()->getJson('/api/v1/merchandise/closings/purchased-fabric')->json('data')));
        $this->assertSame([['PFOB', 'PFOB-2026070001', '2026-07-10', 'SUBMITTED']], array_map(fn ($e) => array_values($e), $this->as()->getJson('/api/v1/merchandise/closings/purchased-fabric/2026-07/exceptions')->assertOk()->json('data')));

        // Posted, nothing stands in the way: July closes, once. August's draft is not July's business.
        $draft->update(['status' => 'POSTED']);
        $this->as()->postJson('/api/v1/merchandise/closings/purchased-fabric', ['period' => '2026-07', 'remarks' => 'Count agreed'])->assertOk()->assertJsonPath('data.status', 'CLOSED');
        $row = $this->as()->getJson('/api/v1/merchandise/closings/purchased-fabric')->json('data.0');
        $this->assertSame(['CLOSED', 0, 'Count agreed'], [$row['status'], $row['exceptions'], $row['remarks']]);
        $this->assertNotNull($row['closed_at']);
        $this->assertSame($this->owner->name, $row['closed_by']);
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/closings/purchased-fabric/2026-07/exceptions')->json('data'));
        $this->as()->postJson('/api/v1/merchandise/closings/purchased-fabric', ['period' => 'jul/2026'])->assertUnprocessable()->assertJsonValidationErrors(['period' => 'Jul/2026 is already closed.']);

        // A July document of that kind is no longer moved on.
        $draft->update(['status' => 'POSTED']);
        $this->as()->postJson('/api/v1/merchandise/fabric-obs/PFOB-2026070001/unpost')->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'Jul/2026 is closed for purchased fabric — its documents can no longer be saved or finalised.']);

        // Finished fabric's opening balance has no workflow: October closes at once, and then holds its balances.
        $fleece = FabricType::query()->create(['code' => 'FLC', 'name' => 'Fleece', 'short_name' => 'FLC', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'active' => true])->id;
        $line = ['description' => 'Navy fleece', 'fabric_item' => 'Fleece 280', 'fabric_type_id' => $fleece, 'fabric_group' => 'BODY', 'uom' => 'KG', 'qty' => 10];
        $this->as()->postJson('/api/v1/merchandise/ffab-obs', ['store_id' => $this->store, 'ob_date' => '2026-10-01', 'currency' => 'BDT', 'lines' => [$line]])->assertCreated();
        $this->as()->postJson('/api/v1/merchandise/closings/finished-fabric', ['period' => 'Oct/2026'])->assertOk();
        $this->as()->postJson('/api/v1/merchandise/ffab-obs', ['store_id' => $this->store, 'ob_date' => '2026-10-05', 'currency' => 'BDT', 'lines' => [$line]])->assertUnprocessable()
            ->assertJsonValidationErrors(['ob_date' => 'Oct/2026 is closed for finished fabric — its documents can no longer be saved or finalised.']);
        $this->as()->deleteJson('/api/v1/merchandise/ffab-obs/FFOB-2026100001')->assertUnprocessable();
        // …but a different kind's month is untouched.
        $this->as()->postJson('/api/v1/merchandise/ffab-obs', ['store_id' => $this->store, 'ob_date' => '2026-07-05', 'currency' => 'BDT', 'lines' => [$line]])->assertCreated();

        // The kinds with no documents yet close straight away; an unknown kind is not a closing.
        foreach (['trims', 'batch-fabric', 'grey-leftover', 'finished-leftover'] as $kind) {
            $this->as()->postJson("/api/v1/merchandise/closings/{$kind}", ['period' => 'Sep/2026'])->assertOk();
        }
        $this->as()->getJson('/api/v1/merchandise/closings/yarn')->assertNotFound();

        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($storeManager)->getJson('/api/v1/merchandise/closings/trims')->assertOk();
        $this->as($storeManager)->postJson('/api/v1/merchandise/closings/trims', ['period' => 'Aug/2026'])->assertForbidden();
    }

    private function pfob(string $no, string $date, string $status): FabricOpeningBalance
    {
        return FabricOpeningBalance::query()->create(['ob_no' => $no, 'unit_company_id' => $this->delta->id, 'store_id' => $this->store, 'ob_date' => $date, 'currency' => 'BDT', 'conv_rate' => 1, 'source' => 'PHYSICAL_COUNT', 'status' => $status]);
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
