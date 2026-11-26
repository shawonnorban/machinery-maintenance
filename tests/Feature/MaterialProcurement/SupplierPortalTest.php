<?php

declare(strict_types=1);

namespace Tests\Feature\MaterialProcurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\MaterialProcurement\Models\QuotationRevision;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Material Procurement → Supplier Portal (AnnotechERP 08/04): a supplier's
 * portal login sees every open line of a submitted requisition, quotes a
 * price, lead time and delivery on it, and sends a new price as the next
 * revision while the quotation is under review — never editing an earlier
 * one. It sees its own quotations only; anyone without a login is told the
 * screens are the suppliers'.
 */
class SupplierPortalTest extends TestCase
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

    public function test_a_supplier_quotes_an_open_line_and_revises_its_price_as_new_revisions(): void
    {
        $poly = $this->supplierLogin('SUP-002', 'Poly Plan Ltd', 'nasir@polyplan.test');
        $item = $this->as()->postJson('/api/v1/erp/material-fabrics', ['item_code' => 'FAB-001', 'name' => 'Cotton Jersey', 'category' => 'Knit', 'gsm' => '160', 'colour' => 'White', 'uom' => 'Kg'])->assertCreated()->json('data.id');
        $line = ['kind' => 'FABRIC', 'item_id' => $item, 'req_qty' => 120, 'uom' => 'KG', 'req_date' => '2026-11-20', 'specification' => '160 GSM combed'];
        $pr = $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'submit' => true, 'lines' => [$line]])->assertCreated()->json('data.pr_no');
        // A draft is nobody's requirement yet.
        $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'lines' => [$line]])->assertCreated();

        $this->assertTrue($this->as($poly)->getJson('/api/v1/auth/me')->json('data.user.portal_only'));
        $this->assertFalse($this->as()->getJson('/api/v1/auth/me')->json('data.user.portal_only'));

        $dash = $this->as($poly)->getJson('/api/v1/material-procurement/portal/dashboard')->assertOk()->json('data');
        $this->assertSame(['Poly Plan Ltd', ['pending' => 1, 'submitted' => 0, 'approved' => 0, 'ended' => 0]], [$dash['supplier'], $dash['counts']]);
        $this->assertSame("{$pr}: 1 material line(s) are open for quotation.", $dash['notifications'][0]['body']);

        $req = $this->as($poly)->getJson('/api/v1/material-procurement/portal/requirements')->assertOk()->json('data');
        $this->assertSame(['USD', 'BDT', 'EUR', 'GBP', 'CNY', 'INR'], $req['currencies']);
        $this->assertCount(1, $req['lines']);
        $open = $req['lines'][0];
        $this->assertSame([$pr, 'DIRECT', 'Cotton Jersey', 'Fabric · White · 160 GSM · 160 GSM combed', 'KG', null], [$open['pr_no'], $open['req_type'], $open['item_name'], $open['item_detail'], $open['uom'], $open['quotation']]);

        $url = "/api/v1/material-procurement/portal/requirements/{$open['line_id']}/quote";
        $this->as($poly)->postJson($url, ['quoted_qty' => 0])->assertUnprocessable()
            ->assertJsonPath('errors.quoted_qty.0', 'Quoted qty must be a number greater than zero.')->assertJsonPath('errors.unit_price.0', 'Unit price must be a number greater than zero.')
            ->assertJsonPath('errors.currency.0', 'Choose the currency.')->assertJsonPath('errors.lead_time_days.0', 'Lead time is required, in whole days.')
            ->assertJsonPath('errors.expected_delivery.0', 'Expected delivery is required.');
        $price = ['quoted_qty' => 120, 'unit_price' => 2.85, 'currency' => 'USD', 'lead_time_days' => 14, 'expected_delivery' => '2026-11-15', 'remarks' => 'Ex-mill'];
        $first = $this->as($poly)->postJson($url, $price)->assertCreated()->json('data');
        $this->assertSame(['QT-'.now()->format('Y').'-00001', 1, 'PENDING'], [$first['qt_no'], $first['revision'], $first['status']]);
        $second = $this->as($poly)->postJson($url, ['unit_price' => 2.70, 'remarks' => null] + $price)->assertCreated()->json('data');
        $this->assertSame([$first['qt_no'], 2, 'REVISED'], [$second['qt_no'], $second['revision'], $second['status']]);
        // The first price stands as it was.
        $this->assertEquals([2.85, 2.70], QuotationRevision::query()->orderBy('revision')->pluck('unit_price')->map(fn ($p) => (float) $p)->all());

        $history = $this->as($poly)->getJson('/api/v1/material-procurement/portal/quotations')->assertOk()->json('data.quotations');
        $this->assertCount(1, $history);
        $this->assertSame(['REVISED', true, 2], [$history[0]['status'], $history[0]['revisable'], count($history[0]['revisions'])]);
        $this->assertEquals([2.70, 'Ex-mill', 14], [$history[0]['latest']['unit_price'], $history[0]['revisions'][0]['remarks'], $history[0]['latest']['lead_time_days']]);
        $line0 = $this->as($poly)->getJson('/api/v1/material-procurement/portal/requirements')->json('data.lines.0.quotation');
        $this->assertSame(['REVISED', 2], [$line0['status'], $line0['revisions']]);
        $this->assertSame(1, $this->as($poly)->getJson('/api/v1/material-procurement/portal/dashboard')->json('data.counts.submitted'));

        // Another supplier quotes too, and neither sees the other's.
        $ykk = $this->supplierLogin('SUP-003', 'YKK Bangladesh', 'shafiq@ykk.test');
        $this->assertNull($this->as($ykk)->getJson('/api/v1/material-procurement/portal/requirements')->json('data.lines.0.quotation'));
        $this->as($ykk)->postJson($url, ['unit_price' => 2.60] + $price)->assertCreated()->assertJsonPath('data.qt_no', 'QT-'.now()->format('Y').'-00002');
        $this->assertCount(1, $this->as($ykk)->getJson('/api/v1/material-procurement/portal/quotations')->json('data.quotations'));
        // The requisition counts both.
        $this->assertSame(2, $this->as()->getJson("/api/v1/material-procurement/requisitions/{$pr}")->json('data.lines.0.quotations'));

        // A decided quotation is revised no more.
        Quotation::query()->where('qt_no', $first['qt_no'])->update(['status' => 'APPROVED']);
        $this->as($poly)->postJson($url, $price)->assertUnprocessable()->assertJsonPath('errors.line.0', 'This quotation is Approved and can no longer be revised.');
        $this->assertSame(1, $this->as($poly)->getJson('/api/v1/material-procurement/portal/dashboard')->json('data.counts.approved'));
    }

    public function test_only_open_lines_are_quoted_and_the_screens_are_the_suppliers(): void
    {
        $poly = $this->supplierLogin('SUP-002', 'Poly Plan Ltd', 'nasir@polyplan.test');
        $item = $this->as()->postJson('/api/v1/erp/material-fabrics', ['item_code' => 'FAB-001', 'name' => 'Cotton Jersey', 'category' => 'Knit', 'uom' => 'Kg'])->assertCreated()->json('data.id');
        $draft = $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'lines' => [['kind' => 'FABRIC', 'item_id' => $item, 'req_qty' => 5, 'uom' => 'KG', 'req_date' => '2026-11-20']]])
            ->assertCreated()->json('data');
        $price = ['quoted_qty' => 5, 'unit_price' => 3, 'currency' => 'BDT', 'lead_time_days' => 7, 'expected_delivery' => '2026-11-15'];
        $this->as($poly)->postJson("/api/v1/material-procurement/portal/requirements/{$draft['lines'][0]['id']}/quote", $price)->assertUnprocessable()
            ->assertJsonPath('errors.line.0', 'This requirement is no longer open for quotation.');

        // The owner holds every permission but no portal login.
        $this->assertNull($this->as()->getJson('/api/v1/material-procurement/portal/dashboard')->assertOk()->json('data.supplier'));
        $this->assertSame([], $this->as()->getJson('/api/v1/material-procurement/portal/requirements')->json('data.lines'));
        $this->as()->postJson("/api/v1/material-procurement/portal/requirements/{$draft['lines'][0]['id']}/quote", $price)->assertUnprocessable()
            ->assertJsonPath('errors.supplier.0', 'This screen is for supplier portal accounts. Ask the Super Admin for a supplier login.');
        // Staff without the portal permission are refused outright.
        $this->as(TenantFixture::user($this->delta, 'STOREKEEPER', 'store@delta.test'))->getJson('/api/v1/material-procurement/portal/requirements')->assertForbidden();
    }

    /** A supplier and its portal login; the login's account. */
    private function supplierLogin(string $code, string $name, string $email): User
    {
        $supplier = $this->as()->postJson('/api/v1/erp/material-suppliers', ['code' => $code, 'name' => $name, 'supplier_type' => 'LOCAL_NON_EPZ', 'goods_service' => 'Fabric'])->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/material-procurement/supplier-logins', ['supplier_id' => $supplier, 'contact_name' => $name.' Desk', 'email' => $email, 'password' => 'Quote-Ready-2026!'])->assertCreated();

        return User::query()->where('email', $email)->firstOrFail();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
