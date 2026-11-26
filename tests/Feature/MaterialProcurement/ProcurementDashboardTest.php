<?php

declare(strict_types=1);

namespace Tests\Feature\MaterialProcurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Material Procurement → Procurement Dashboard (AnnotechERP 08/02): the eight
 * counts as Brickland keeps them, the latest requisitions with their lines,
 * and the approved lines being followed up — the ones still awaited first,
 * with their balance; whoever works in the module opens it, a supplier's
 * portal login does not.
 */
class ProcurementDashboardTest extends TestCase
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

    public function test_the_dashboard_counts_requisitions_quotations_approvals_and_deliveries(): void
    {
        $merch = TenantFixture::user($this->delta, 'STORE_MANAGER', 'merch@delta.test');
        $poly = $this->as()->postJson('/api/v1/erp/material-suppliers', ['code' => 'SUP-002', 'name' => 'Poly Plan Ltd', 'supplier_type' => 'LOCAL_NON_EPZ'])->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/erp/material-suppliers', ['code' => 'SUP-003', 'name' => 'YKK Bangladesh', 'supplier_type' => 'LOCAL_EPZ'])->assertCreated();
        $this->as()->postJson('/api/v1/material-procurement/supplier-logins', ['supplier_id' => $poly, 'contact_name' => 'Poly Desk', 'email' => 'desk@polyplan.test', 'password' => 'Quote-Ready-2026!'])->assertCreated();
        $desk = User::query()->where('email', 'desk@polyplan.test')->firstOrFail();
        $fabric = $this->as()->postJson('/api/v1/erp/material-fabrics', ['item_code' => 'FAB-001', 'name' => 'Cotton Jersey', 'category' => 'Knit', 'uom' => 'Kg'])->assertCreated()->json('data.id');
        $rib = $this->as()->postJson('/api/v1/erp/material-fabrics', ['item_code' => 'FAB-002', 'name' => '1x1 Rib', 'category' => 'Knit', 'uom' => 'Kg'])->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/erp/material-trims', ['item_code' => 'ACC-001', 'name' => 'Main Label', 'category' => 'Label', 'uom' => 'Pcs', 'active' => false])->assertCreated();

        $line = fn (string $item, int $qty): array => ['kind' => 'FABRIC', 'item_id' => $item, 'req_qty' => $qty, 'uom' => 'KG', 'req_date' => '2026-11-20'];
        $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'lines' => [$line($fabric, 10)]])->assertCreated();
        $approved = $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'submit' => true, 'lines' => [$line($fabric, 100)]])->assertCreated()->json('data');
        $open = $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'submit' => true, 'lines' => [$line($rib, 80), $line($fabric, 20)]])->assertCreated()->json('data');
        $quote = fn (string $l, float $price): string => Quotation::query()->where('qt_no', $this->as($desk)->postJson("/api/v1/material-procurement/portal/requirements/{$l}/quote", ['quoted_qty' => 1, 'unit_price' => $price, 'currency' => 'USD', 'lead_time_days' => 7, 'expected_delivery' => '2026-11-15'])->assertCreated()->json('data.qt_no'))->value('id');

        // One approved and partly received; on the open requisition one quotation pending and one in negotiation.
        $win = $quote($approved['lines'][0]['id'], 2.65);
        $this->as()->postJson("/api/v1/material-procurement/price-review/quotations/{$win}/approve", ['handover_to' => $merch->id])->assertOk();
        $quote($open['lines'][0]['id'], 3.2);
        $haggle = $quote($open['lines'][1]['id'], 2.9);
        $this->as()->postJson("/api/v1/material-procurement/price-review/quotations/{$haggle}/negotiate", ['message' => 'Best price, please.'])->assertOk();
        $this->as($merch)->postJson("/api/v1/material-procurement/assigned-materials/{$approved['lines'][0]['id']}/followups", ['followup_date' => '2026-10-28', 'status' => 'PARTIALLY_RECEIVED', 'received_qty' => 40])->assertCreated();

        $data = $this->as()->getJson('/api/v1/material-procurement/procurement-dashboard')->assertOk()->json('data');
        $this->assertSame(['draft' => 1, 'submitted' => 2, 'open_lines' => 2, 'quotations' => 3, 'awaiting_review' => 1, 'negotiation' => 1, 'approved_lines' => 1, 'moving' => 1], $data['counts']);
        $this->assertSame([2, 2, 1, 0], [$data['active_items'], $data['active_suppliers'], $data['suppliers_with_login'], $data['done']]);
        $this->assertSame([[$open['pr_no'], 'DIRECT', 2, 'SUBMITTED'], [$approved['pr_no'], 'DIRECT', 1, 'APPROVED']], array_map(fn (array $r): array => [$r['pr_no'], $r['req_type'], $r['lines'], $r['status']], array_slice($data['requisitions'], 0, 2)));
        $this->assertCount(3, $data['requisitions']);
        $this->assertEquals([['Cotton Jersey', 'Poly Plan Ltd', 'USD', 2.65, 60, 'KG', 'PARTIALLY_RECEIVED']], array_map(fn (array $d): array => [$d['item_name'], $d['supplier'], $d['price']['currency'], $d['price']['unit_price'], $d['balance'], $d['uom'], $d['status']], $data['deliveries']));

        // Fully in: done, and listed after anything still awaited.
        $this->as($merch)->postJson("/api/v1/material-procurement/assigned-materials/{$approved['lines'][0]['id']}/followups", ['followup_date' => '2026-10-30', 'status' => 'IN_HOUSE', 'received_qty' => 60])->assertCreated();
        $data = $this->as()->getJson('/api/v1/material-procurement/procurement-dashboard')->json('data');
        $this->assertSame([1, 0, 0.0], [$data['done'], $data['counts']['moving'], (float) $data['deliveries'][0]['balance']]);

        // Whoever works in the module; not a supplier's portal login.
        $this->as(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test'))->getJson('/api/v1/material-procurement/procurement-dashboard')->assertOk();
        $this->as($desk)->getJson('/api/v1/material-procurement/procurement-dashboard')->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
