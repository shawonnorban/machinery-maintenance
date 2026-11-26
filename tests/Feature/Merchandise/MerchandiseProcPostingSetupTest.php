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
 * Trims & Accessories → Procurement Posting Setup (AnnotechERP 06/06): one
 * setup per company, its voucher type and five postable ledgers required;
 * a receipt finalised only while its company's setup is active; a setup a
 * finalised receipt stands on kept.
 */
class MerchandiseProcPostingSetupTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private mixed $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_one_setup_per_company_with_its_ledgers_gates_finalising_a_receipt(): void
    {
        $ledger = fn (string $code, string $name, bool $postable = true) => AccAccount::query()->create(['company_id' => $this->delta->id, 'code' => $code, 'name' => $name, 'type' => 'ASSET', 'postable' => $postable, 'active' => true])->id;
        $group = $ledger('1000', 'Current Assets', false);
        $books = ['inventory_ledger_id' => $ledger('1120', 'Inventory — Trims'), 'vat_ledger_id' => $ledger('1210', 'VAT Receivable'), 'payable_ledger_id' => $ledger('2020201', 'Accounts Payable'),
            'tds_ledger_id' => $ledger('2030101', 'TDS Payable'), 'vds_ledger_id' => $ledger('2030102', 'VDS Payable')];

        $this->assertSame([], $this->as()->getJson('/api/v1/erp/proc-posting-setups')->assertOk()->json('data'));
        $this->as()->postJson('/api/v1/erp/proc-posting-setups', [])->assertUnprocessable()->assertJsonValidationErrors([
            'unit_company_id' => 'Company is required.', 'voucher_type' => 'Voucher Type is required.', 'inventory_ledger_id' => 'Inventory (Dr) is required.', 'vat_ledger_id' => 'VAT Receivable (Dr) is required.',
            'payable_ledger_id' => 'Supplier Payable (Cr) is required.', 'tds_ledger_id' => 'TDS Payable (Cr) is required.', 'vds_ledger_id' => 'VDS Payable (Cr) is required.',
        ]);
        // A group heading is not posted to.
        $this->as()->postJson('/api/v1/erp/proc-posting-setups', ['unit_company_id' => $this->delta->id, 'voucher_type' => 'JV', ...$books, 'inventory_ledger_id' => $group])->assertUnprocessable()->assertJsonValidationErrors(['inventory_ledger_id']);

        $setup = $this->as()->postJson('/api/v1/erp/proc-posting-setups', ['unit_company_id' => $this->delta->id, 'voucher_type' => 'JV', ...$books, 'active' => false])->assertCreated()->json('data');
        $this->assertSame(['Delta Apparels Ltd', 'JV', '1120 — Inventory — Trims', '1210 — VAT Receivable', '2020201 — Accounts Payable', '2030101 — TDS Payable', '2030102 — VDS Payable', false],
            [$setup['company'], $setup['voucher_type'], $setup['inventory_ledger'], $setup['vat_ledger'], $setup['payable_ledger'], $setup['tds_ledger'], $setup['vds_ledger'], $setup['active']]);
        $this->as()->postJson('/api/v1/erp/proc-posting-setups', ['unit_company_id' => $this->delta->id, 'voucher_type' => 'BV', ...$books])->assertUnprocessable()
            ->assertJsonValidationErrors(['unit_company_id' => 'This company already has a posting setup — edit that one instead.']);

        // An inactive setup does not finalise a receipt; switched on, it does.
        $no = $this->receipt();
        $this->as()->postJson("/api/v1/merchandise/acc-receipts/{$no}/finalize")->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->as()->patchJson("/api/v1/erp/proc-posting-setups/{$setup['id']}", ['active' => true, 'voucher_type' => 'BV'])->assertOk()->assertJsonPath('data.voucher_type', 'BV');
        $this->as()->postJson("/api/v1/merchandise/acc-receipts/{$no}/finalize")->assertOk()->assertJsonPath('data.status', 'FINALIZED');

        // A setup a finalised receipt stands on is kept.
        $this->as()->deleteJson("/api/v1/erp/proc-posting-setups/{$setup['id']}")->assertUnprocessable();

        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($storeManager)->getJson('/api/v1/erp/proc-posting-setups')->assertOk();
        $this->as($storeManager)->patchJson("/api/v1/erp/proc-posting-setups/{$setup['id']}", ['active' => false])->assertForbidden();
    }

    private function receipt(): string
    {
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Trims Store', 'code' => 'ST-TRM', 'active' => true])->id;
        $item = MaterialItem::query()->create(['kind' => 'TRIMS', 'item_code' => 'TR-BTN-01', 'name' => 'Button 18L', 'category' => 'Button', 'uom' => 'Pcs', 'active' => true])->id;
        $supplier = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Star Accessories', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;

        return $this->as()->postJson('/api/v1/merchandise/acc-receipts', ['unit_company_id' => $this->delta->id, 'supplier_id' => $supplier, 'store_id' => $store, 'mrr_date' => '2026-10-20', 'payment_method' => 'CASH',
            'source_type' => 'LOCAL', 'challan_no' => 'CH-1', 'challan_date' => '2026-10-20', 'currency' => 'BDT', 'mrr_type' => 'PO', 'lines' => [['item_id' => $item, 'qty' => 10, 'rate' => 1]], 'costs' => []])->assertCreated()->json('data.mrr_no');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
