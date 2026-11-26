<?php

declare(strict_types=1);

namespace Tests\Feature\PreBudget;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;

/**
 * Pre-Budget tests' company: Delta with its owner, a factory, the C&A buyer and
 * the masters a requisition picks from, a complete knit requisition, and a
 * token for the owner.
 */
trait PreBudgetFixture
{
    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $c = $this->delta->id;
        TenantFixture::factory($this->delta, 'Norban Composite', 'NCL');
        Buyer::query()->create(['company_id' => $c, 'code' => 'CNA', 'name' => 'C&A Buying', 'short_name' => 'C&A', 'pi_address' => 'EU', 'registered_from' => '2026-01-01', 'active' => true]);
        $row = fn (array $v) => ['id' => strtolower((string) Str::ulid()), 'company_id' => $c, 'created_at' => now(), 'updated_at' => now()] + $v;
        DB::table('item_types')->insert($row(['item_group' => 'KNIT_TOP', 'code' => 'TS', 'name' => 'T-Shirt', 'sort_order' => 1, 'active' => true]));
        DB::table('fabric_types')->insert($row(['code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'S/J', 'construction' => 'KNIT', 'sort_order' => 1, 'active' => true]));
        DB::table('fiber_compositions')->insert($row(['code' => 'CTN100', 'description' => '100% Cotton', 'active' => true]));
        DB::table('garment_parts')->insert($row(['code' => 'BODY', 'name' => 'Body', 'active' => true]));
        DB::table('knit_yarn_counts')->insert($row(['count_no' => 'YC-30-1', 'count_system' => 'Ne', 'description' => '30/1', 'active' => true]));
        DB::table('mp_suppliers')->insert($row(['code' => 'SUP-1', 'name' => 'Huatai Textile', 'supplier_type' => 'FOREIGN', 'active' => true]));
        foreach (['ORDER_TYPE' => 'Confirmed PO', 'WASH_TYPE' => 'Normal Wash', 'GSM' => '160 GSM', 'YARN_TYPE' => '100% Cotton', 'SUSTAINABILITY' => 'BCI',
            // Norban's requisition lists, Pre-Budget's own since 2026-11-20.
            'ITEM' => 'T-Shirt', 'FABRIC_TYPE' => 'Single Jersey', 'FABRIC_COMPOSITION' => '100% Cotton', 'YARN_COMPOSITION' => '100% Cotton', 'PARTS' => 'Body', 'YARN_COUNT' => '30/1'] as $list => $name) {
            DB::table('pb_list_values')->insert($row(['list' => $list, 'name' => $name, 'sort_order' => 10, 'active' => true]));
        }
        $brand = DB::table('lookup_tables')->whereNull('company_id')->where('code', 'BRAND')->value('id');
        DB::table('lookup_values')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $c, 'lookup_table_id' => $brand, 'code' => 'CLOCKHOUSE', 'name' => 'Clockhouse', 'sort_order' => 10, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array<string, mixed> a knit requisition with every required column */
    private function knit(): array
    {
        return [
            'entry_type' => 'Knit', 'factory' => 'NCL', 'buyer' => 'C&A', 'brand' => 'Clockhouse', 'season' => 'SS-26', 'dept' => 'Mens', 'style' => '2261582',
            'order_type' => 'Confirmed PO', 'artwork' => strtoupper((string) Str::ulid()), 'item' => 'T-Shirt', 'wash_type' => 'Normal Wash', 'order_color' => 'Navy',
            'fabric_type' => 'Single Jersey', 'gsm' => '160 GSM', 'parts' => 'Body', 'yarn_type' => '100% Cotton', 'yarn_count' => '30/1', 'composition' => '100% Cotton',
            'quantity_ticket' => 1000, 'pac' => 2, 'fob_price' => 3.5, 'quantity_pcs' => 2000, 'cutting_percent' => 5,
            'cad_consumption' => 5, 'process_loss_percent' => 10, 'feeding_percent' => 100, 'sample_qty' => 20,
        ];
    }

    private function as(): static
    {
        $this->flushHeaders();

        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
