<?php

declare(strict_types=1);

namespace Tests\Feature\MaterialProcurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Vendor\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Material Procurement → Master Data (AnnotechERP 08/01): fabric and trims
 * items, each kind its own list with its own codes; the procurement
 * suppliers, apart from Machinery's vendors; switched off, never deleted; and
 * who may change them.
 */
class MaterialProcurementMasterTest extends TestCase
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

    public function test_fabric_and_trims_items_keep_their_own_lists_and_codes(): void
    {
        $fabric = ['item_code' => 'fab-001', 'name' => 'Cotton Jersey', 'category' => 'Knit / Single Jersey', 'gsm' => '160', 'width' => '60"', 'composition' => '100% Cotton', 'colour' => 'White', 'uom' => 'Kg'];

        $id = $this->as()->postJson('/api/v1/erp/material-fabrics', $fabric + ['kind' => 'TRIMS'])->assertCreated()
            ->assertJsonPath('data.item_code', 'FAB-001')->assertJsonPath('data.active', true)->assertJsonPath('data.width', '60"')->json('data.id');
        $this->as()->postJson('/api/v1/erp/material-fabrics', $fabric)->assertUnprocessable()->assertJsonValidationErrors('item_code');
        $this->as()->postJson('/api/v1/erp/material-fabrics', ['item_code' => ''] + $fabric)->assertUnprocessable()->assertJsonPath('errors.item_code.0', 'Item code is required.');
        $this->as()->postJson('/api/v1/erp/material-fabrics', ['uom' => null] + $fabric + ['item_code' => 'FAB-002'])->assertUnprocessable()->assertJsonValidationErrors('uom');

        // Trims may use the same code; each list shows only its own kind.
        $this->as()->postJson('/api/v1/erp/material-trims', ['item_code' => 'FAB-001', 'name' => 'Main Label', 'category' => 'Label', 'size' => '50 x 20 mm', 'uom' => 'Pcs'])->assertCreated()->assertJsonPath('data.size', '50 x 20 mm');
        $this->as()->getJson('/api/v1/erp/material-fabrics')->assertJsonCount(1, 'data')->assertJsonPath('data.0.composition', '100% Cotton');
        $this->as()->getJson('/api/v1/erp/material-trims')->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.gsm');

        // Switched off by the Status switch, never deleted.
        $this->as()->patchJson("/api/v1/erp/material-fabrics/{$id}", ['active' => false])->assertOk()->assertJsonPath('data.active', false)->assertJsonPath('data.name', 'Cotton Jersey');
        $this->as()->deleteJson("/api/v1/erp/material-fabrics/{$id}")->assertStatus(405);
    }

    public function test_suppliers_are_procurements_own(): void
    {
        $supplier = ['code' => 'sup-001', 'name' => 'Koyo Trims Solution', 'short_name' => 'KOYO', 'supplier_type' => 'LOCAL_NON_EPZ', 'owner_name' => 'M. Rahman', 'goods_service' => 'Trims', 'vat_reg_no' => '000123456-0101', 'tin_no' => '123456789012', 'address' => 'Gazipur'];

        $id = $this->as()->postJson('/api/v1/erp/material-suppliers', $supplier)->assertCreated()
            ->assertJsonPath('data.code', 'SUP-001')->assertJsonPath('data.active', true)->assertJsonPath('data.portal_logins', 0)->json('data.id');
        $this->as()->postJson('/api/v1/erp/material-suppliers', ['supplier_type' => 'OVERSEAS'] + $supplier + ['code' => 'SUP-002'])->assertUnprocessable()->assertJsonValidationErrors('supplier_type');
        $this->as()->postJson('/api/v1/erp/material-suppliers', $supplier)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->as()->postJson('/api/v1/erp/material-suppliers', ['code' => ''] + $supplier)->assertUnprocessable()->assertJsonPath('errors.code.0', 'Supplier code is required.');

        $supplier = MaterialSupplier::query()->findOrFail($id);
        $this->assertSame(['KOYO', 'LOCAL_NON_EPZ', 'M. Rahman', '000123456-0101'], [$supplier->short_name, $supplier->supplier_type, $supplier->owner_name, $supplier->vat_reg_no]);
        $this->assertSame($this->owner->id, $supplier->created_by);
        // Machinery's vendor register is not touched.
        $this->assertSame(0, Vendor::query()->where('code', 'SUP-001')->count());

        $this->as()->patchJson("/api/v1/erp/material-suppliers/{$id}", ['active' => false])->assertOk()->assertJsonPath('data.active', false);
        $this->as()->deleteJson("/api/v1/erp/material-suppliers/{$id}")->assertStatus(405);
    }

    public function test_managers_read_the_masters_and_technicians_do_not(): void
    {
        $id = $this->as()->postJson('/api/v1/erp/material-trims', ['item_code' => 'ACC-001', 'name' => 'Main Label', 'category' => 'Label', 'uom' => 'Pcs'])->json('data.id');

        $manager = $this->token(TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test'));
        $this->withToken($manager)->getJson('/api/v1/erp/material-trims')->assertOk();
        $this->withToken($manager)->patchJson("/api/v1/erp/material-trims/{$id}", ['active' => false])->assertForbidden();

        $store = $this->token(TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'store@delta.test'));
        $this->withToken($store)->patchJson("/api/v1/erp/material-trims/{$id}", ['active' => false])->assertOk();

        $technician = $this->token(TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test'));
        $this->withToken($technician)->getJson('/api/v1/erp/material-suppliers')->assertForbidden();
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
