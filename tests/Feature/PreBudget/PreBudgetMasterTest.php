<?php

declare(strict_types=1);

namespace Tests\Feature\PreBudget;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Api\Support\ApiCaller;
use App\Modules\Erp\Support\ResourceSeeder;
use App\Modules\Erp\Support\SeedTokenHandle;
use App\Modules\Identity\Models\User;
use App\Modules\PreBudget\Database\Seeders\PreBudgetMasterSeed;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Pre-Budget → Master Data (AnnotechERP 33/34, plan 2026-11-19): the module's
 * own short lists (one table, told apart by list), the Accessories Item list
 * with its next ACC number and UOM-master unit, Norban's lists brought in,
 * switched off never deleted, who may see and change them, and the menu.
 */
class PreBudgetMasterTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_each_short_list_keeps_its_own_names(): void
    {
        $id = $this->as()->postJson('/api/v1/erp/pb-gsms', ['name' => ' 160 GSM ', 'description' => 'Single jersey', 'sort_order' => 30])->assertCreated()
            ->assertJsonPath('data.name', '160 GSM')->assertJsonPath('data.active', true)->assertJsonPath('data.sort_order', 30)->json('data.id');
        // Unique within its list, without case; another list may hold the same name.
        $this->as()->postJson('/api/v1/erp/pb-gsms', ['name' => '160 gsm'])->assertUnprocessable()->assertJsonPath('errors.name.0', 'This name is already in the list.');
        $this->as()->postJson('/api/v1/erp/pb-order-types', ['name' => '160 GSM'])->assertCreated();
        $this->as()->postJson('/api/v1/erp/pb-gsms', ['description' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->as()->getJson('/api/v1/erp/pb-gsms')->assertJsonCount(1, 'data');
        $this->as()->getJson('/api/v1/erp/pb-order-types')->assertJsonCount(1, 'data');
        $this->assertSame(['GSM', 'ORDER_TYPE'], DB::table('pb_list_values')->orderBy('list')->pluck('list')->all());
        $this->assertSame($this->owner->id, DB::table('pb_list_values')->where('id', $id)->value('created_by'));

        // Switched off by the Status switch, never deleted.
        $this->as()->patchJson("/api/v1/erp/pb-gsms/{$id}", ['active' => false])->assertOk()->assertJsonPath('data.active', false)->assertJsonPath('data.name', '160 GSM');
        $this->as()->deleteJson("/api/v1/erp/pb-gsms/{$id}")->assertStatus(405);
    }

    public function test_an_accessory_gets_the_next_code_and_a_unit_from_the_uom_master(): void
    {
        $this->as()->postJson('/api/v1/erp/pb-accessory-items', ['name' => 'Care Label', 'category' => 'Label', 'uom' => 'pcs'])->assertCreated()
            ->assertJsonPath('data.code', 'ACC-0001')->assertJsonPath('data.uom', 'PCS');
        $this->as()->postJson('/api/v1/erp/pb-accessory-items', ['name' => 'Hang Tag', 'code' => 'acc-0010'])->assertCreated()->assertJsonPath('data.code', 'ACC-0010');
        $id = $this->as()->postJson('/api/v1/erp/pb-accessory-items', ['name' => 'Sewing Thread', 'uom' => 'CONE'])->assertCreated()->assertJsonPath('data.code', 'ACC-0011')->json('data.id');

        // A name or code twice, or a unit the UOM master lacks, is refused.
        $this->as()->postJson('/api/v1/erp/pb-accessory-items', ['name' => 'care label'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->as()->postJson('/api/v1/erp/pb-accessory-items', ['name' => 'Poly Bag', 'code' => 'ACC-0001'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->as()->postJson('/api/v1/erp/pb-accessory-items', ['name' => 'Poly Bag', 'uom' => 'BUCKET'])->assertUnprocessable()->assertJsonValidationErrors('uom');

        // Editing keeps the code; blanking it does not lose it.
        $this->as()->patchJson("/api/v1/erp/pb-accessory-items/{$id}", ['category' => 'Thread', 'code' => ''])->assertOk()->assertJsonPath('data.code', 'ACC-0011')->assertJsonPath('data.category', 'Thread');
        $this->as()->deleteJson("/api/v1/erp/pb-accessory-items/{$id}")->assertStatus(405);
    }

    public function test_norbans_lists_are_brought_in_once(): void
    {
        $data = json_decode((string) file_get_contents(base_path('app/Modules/PreBudget/Database/Data/norban_masters.json')), true);
        // As erp:seed-masters runs it: through the screens, as the owner.
        app()->instance(ApiCaller::class, ApiCaller::forUser(new SeedTokenHandle($this->delta->id, $this->owner->id), $this->owner));
        (new PreBudgetMasterSeed)->seed($seeder = app(ResourceSeeder::class));
        $this->assertSame([], $seeder->skipped());

        foreach (['YARN_TYPE', 'WASH_TYPE', 'ADDITIONAL_PROCESS', 'GSM', 'SUSTAINABILITY', 'ORDER_TYPE', 'ITEM', 'FABRIC_TYPE', 'FABRIC_COMPOSITION', 'YARN_COMPOSITION', 'PARTS', 'YARN_COUNT'] as $list) {
            // Every Norban name is in the list once (the fixture's own values stay beside them).
            $names = array_map(fn ($v) => mb_strtolower($v['name']), $data['lists'][$list]);
            $this->assertSame(count($names), DB::table('pb_list_values')->where('company_id', $this->delta->id)->where('list', $list)->whereIn(DB::raw('lower(name)'), $names)->count(), $list);
        }
        // Brand / Season / Dept: Norban's names among the company's lookup values.
        $brand = DB::table('lookup_tables')->whereNull('company_id')->where('code', 'BRAND')->value('id');
        $this->assertSame(count($data['lookups']['BRAND']), DB::table('lookup_values')->where('lookup_table_id', $brand)->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $this->delta->id))
            ->whereIn(DB::raw('lower(name)'), array_map(fn ($v) => mb_strtolower($v['name']), $data['lookups']['BRAND']))->count());
        $this->assertSame(226, DB::table('pb_accessory_items')->where('company_id', $this->delta->id)->count());
        // Norban's units arrive as UOM-master codes (its CON is CONE).
        $this->assertSame([], array_values(array_diff(DB::table('pb_accessory_items')->whereNotNull('uom')->distinct()->pluck('uom')->all(), DB::table('units_of_measure')->whereNull('company_id')->pluck('code')->all())));

        // Again: nothing new.
        (new PreBudgetMasterSeed)->seed($again = app(ResourceSeeder::class));
        $this->assertSame([], array_filter($again->created()));
        $this->assertSame(226, DB::table('pb_accessory_items')->where('company_id', $this->delta->id)->count());
    }

    public function test_who_may_see_and_change_the_masters_and_the_menu(): void
    {
        $id = $this->as()->postJson('/api/v1/erp/pb-wash-types', ['name' => 'Enzyme Wash'])->json('data.id');

        $manager = $this->token(TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test'));
        $this->withToken($manager)->getJson('/api/v1/erp/pb-wash-types')->assertOk();
        $this->withToken($manager)->patchJson("/api/v1/erp/pb-wash-types/{$id}", ['active' => false])->assertForbidden();

        $admin = $this->token(TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'admin@delta.test'));
        $this->withToken($admin)->patchJson("/api/v1/erp/pb-wash-types/{$id}", ['active' => false])->assertOk();

        // Store fills the stock yarn use only; the masters stay closed to it.
        $store = $this->token(TenantFixture::user($this->delta, 'STOREKEEPER', 'store@delta.test'));
        $this->withToken($store)->getJson('/api/v1/erp/pb-wash-types')->assertForbidden();

        // Pre-Budget laid out like Order Summary (user, 2026-11-21): the Dashboard on its own at the top (2026-11-22),
        // then Requisition, Budget, Accessories Budget and Setup — the two Column Setups and the lists' screen, no Master Data module of their own.
        $this->artisan('access:sync-menus')->assertSuccessful();
        $modules = collect($this->as()->getJson('/api/v1/access/navigation')->assertOk()->json('data'))->keyBy('code');
        $this->assertArrayNotHasKey('PRE_BUDGET_MASTER', $modules->all());
        $this->assertSame('PRE_BUDGET', $modules['PRE_BUDGET']['section']);
        $folders = collect($modules['PRE_BUDGET']['entries'])->keyBy('code');
        $this->assertSame(['PRE_BUDGET.DASHBOARD', 'PRE_BUDGET.REQUISITION', 'PRE_BUDGET.BUDGET', 'PRE_BUDGET.ACCESSORIES', 'PRE_BUDGET.SETUP'], $folders->keys()->all());
        $this->assertSame('screen', $folders['PRE_BUDGET.DASHBOARD']['type']);
        $this->assertSame(
            ['PRE_BUDGET_MASTER.REQUISITION_COLUMN_SETUP', 'PRE_BUDGET_MASTER.ACC_BUDGET_COLUMN_SETUP', 'PRE_BUDGET_MASTER.MASTER_DATA'],
            collect($folders['PRE_BUDGET.SETUP']['items'])->pluck('code')->all(),
        );
        // The section sits right after Sales & Merchandising.
        $sections = $modules->pluck('section')->unique()->values()->all();
        $this->assertSame('PRE_BUDGET', $sections[array_search('SALES_MERCHANDISING', $sections, true) + 1]);
    }

    private function token(User $user): string
    {
        return app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token')['plain'];
    }

    private function as(): static
    {
        return $this->withToken($this->token($this->owner));
    }
}
