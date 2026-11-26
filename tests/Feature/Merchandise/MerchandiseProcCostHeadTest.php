<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Accounting\Models\AccAccount;
use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Trims & Accessories → Procurement Cost Head (AnnotechERP 06/06): the five
 * heads a company starts with; a head not landed needing the ledger it is
 * expensed on; codes upper-case and unique; a receipt keeping the flags it
 * was saved with when the head changes; a head in use not removed.
 */
class MerchandiseProcCostHeadTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    private mixed $factory;

    public function test_cost_heads_are_kept_with_their_flags_and_ledger_and_a_receipt_keeps_what_it_was_saved_with(): void
    {
        $heads = $this->as()->getJson('/api/v1/erp/proc-cost-heads')->assertOk()->json('data');
        $this->assertSame([['CARRIAGE', 'Carriage Inward', true, false, 10, true], ['CNF', 'C&F Charges', true, true, 20, true], ['LC-CHARGE', 'LC Charges', true, false, 30, true], ['INSURANCE', 'Insurance', true, false, 40, true], ['LOADING', 'Loading / Unloading', true, false, 50, true]],
            array_map(fn ($h) => [$h['code'], $h['name'], $h['landed'], $h['vat_applicable'], $h['sort_order'], $h['active']], $heads));

        $this->as()->postJson('/api/v1/erp/proc-cost-heads', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'Code is required.', 'name' => 'Cost Head is required.']);
        $this->as()->postJson('/api/v1/erp/proc-cost-heads', ['code' => 'cnf', 'name' => 'Duplicate'])->assertUnprocessable()->assertJsonValidationErrors(['code']);
        // An expensed head names its ledger.
        $this->as()->postJson('/api/v1/erp/proc-cost-heads', ['code' => 'bank-chg', 'name' => 'Bank Charges', 'landed' => false])->assertUnprocessable()
            ->assertJsonValidationErrors(['expense_ledger_id' => 'Expense Ledger is required when the head is not a landed cost.']);
        $ledger = AccAccount::query()->create(['company_id' => $this->delta->id, 'code' => '5310', 'name' => 'Bank Charges Expense', 'type' => 'EXPENSE', 'postable' => true, 'active' => true])->id;
        $this->assertSame([['5310 — Bank Charges Expense']], [array_column($this->as()->getJson('/api/v1/merchandise/acc-receipts/ledgers')->assertOk()->json('data'), 'label')]);
        $bank = $this->as()->postJson('/api/v1/erp/proc-cost-heads', ['code' => ' bank-chg ', 'name' => 'Bank Charges', 'landed' => false, 'expense_ledger_id' => $ledger, 'sort_order' => 60])->assertCreated()->json('data');
        $this->assertSame(['BANK-CHG', false, false, '5310 — Bank Charges Expense', 60, true], [$bank['code'], $bank['landed'], $bank['vat_applicable'], $bank['expense_ledger'], $bank['sort_order'], $bank['active']]);
        // Made landed later, its ledger may go.
        $this->as()->patchJson("/api/v1/erp/proc-cost-heads/{$bank['id']}", ['expense_ledger_id' => null])->assertUnprocessable()->assertJsonValidationErrors(['expense_ledger_id']);
        $this->as()->patchJson("/api/v1/erp/proc-cost-heads/{$bank['id']}", ['landed' => true, 'expense_ledger_id' => null])->assertOk()->assertJsonPath('data.landed', true);

        // A receipt copies the head's flags when it is saved.
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Trims Store', 'code' => 'ST-TRM', 'active' => true])->id;
        $item = MaterialItem::query()->create(['kind' => 'TRIMS', 'item_code' => 'TR-BTN-01', 'name' => 'Button 18L', 'category' => 'Button', 'uom' => 'Pcs', 'active' => true])->id;
        $supplier = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Star Accessories', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $carriage = collect($heads)->firstWhere('code', 'CARRIAGE')['id'];
        $receipt = $this->as()->postJson('/api/v1/merchandise/acc-receipts', ['unit_company_id' => $this->delta->id, 'supplier_id' => $supplier, 'store_id' => $store, 'mrr_date' => '2026-10-20', 'payment_method' => 'CASH', 'source_type' => 'LOCAL',
            'challan_no' => 'CH-1', 'challan_date' => '2026-10-20', 'currency' => 'BDT', 'mrr_type' => 'PO', 'lines' => [['item_id' => $item, 'qty' => 10, 'rate' => 1]], 'costs' => [['cost_head_id' => $carriage, 'amount' => 5]]])->assertCreated()->json('data');
        $this->assertEquals([15, 15], [$receipt['landed_cost'], $receipt['net_payable']]);

        // Expensing carriage from now on never restates the receipt on file.
        $this->as()->patchJson("/api/v1/erp/proc-cost-heads/{$carriage}", ['landed' => false, 'vat_applicable' => true, 'expense_ledger_id' => $ledger])->assertOk();
        $again = $this->as()->getJson("/api/v1/merchandise/acc-receipts/{$receipt['mrr_no']}")->json('data');
        $this->assertEquals([true, false, 15], [$again['costs'][0]['landed'], $again['costs'][0]['vat_applicable'], $again['landed_cost']]);

        // In use, a head stays (switch it off instead); an unused one goes.
        $this->as()->deleteJson("/api/v1/erp/proc-cost-heads/{$carriage}")->assertUnprocessable();
        $this->as()->deleteJson("/api/v1/erp/proc-cost-heads/{$bank['id']}")->assertNoContent();
        $this->as()->patchJson("/api/v1/erp/proc-cost-heads/{$carriage}", ['active' => false])->assertOk();
        $this->assertNotContains($carriage, array_column($this->as()->getJson('/api/v1/merchandise/acc-receipts/options')->json('data.cost_heads'), 'value'));

        // A store manager sees the heads but does not change them.
        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($storeManager)->getJson('/api/v1/erp/proc-cost-heads')->assertOk();
        $this->as($storeManager)->postJson('/api/v1/erp/proc-cost-heads', ['code' => 'X', 'name' => 'X'])->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
