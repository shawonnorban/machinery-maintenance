<?php

declare(strict_types=1);

namespace Tests\Feature\MaterialProcurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MaterialProcurement\Models\AuditEntry;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\MaterialProcurement\Models\SupplierLogin;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Material Procurement → Control (AnnotechERP 08/07): every critical
 * transaction — masters, portal logins, requisitions, quotations and their
 * revisions, price decisions, follow-ups and receipts — leaves a permanent,
 * read-only entry in the module's audit trail, kept in English; the reviewers
 * hear of each quotation and revision; Notifications lists the module's
 * notifications to the caller and marks them read without touching the rest.
 */
class ProcurementControlTest extends TestCase
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

    public function test_every_critical_transaction_leaves_a_permanent_english_entry_in_the_audit_trail(): void
    {
        $merch = TenantFixture::user($this->delta, 'STORE_MANAGER', 'merch@delta.test');
        $supplier = $this->as()->postJson('/api/v1/erp/material-suppliers', ['code' => 'SUP-002', 'name' => 'Poly Plan Ltd', 'supplier_type' => 'LOCAL_NON_EPZ'])->assertCreated()->json('data.id');
        $this->as()->patchJson("/api/v1/erp/material-suppliers/{$supplier}", ['goods_service' => 'Fabric'])->assertOk();
        $this->as()->postJson('/api/v1/material-procurement/supplier-logins', ['supplier_id' => $supplier, 'contact_name' => 'Poly Desk', 'email' => 'desk@polyplan.test', 'password' => 'Quote-Ready-2026!'])->assertCreated();
        $login = SupplierLogin::query()->sole();
        $this->as()->putJson("/api/v1/material-procurement/supplier-logins/{$login->id}/active", ['active' => false])->assertOk();
        $this->as()->putJson("/api/v1/material-procurement/supplier-logins/{$login->id}/active", ['active' => true])->assertOk();
        $poly = User::query()->where('email', 'desk@polyplan.test')->firstOrFail();
        $fabric = $this->as()->postJson('/api/v1/erp/material-fabrics', ['item_code' => 'FAB-001', 'name' => 'Cotton Jersey', 'category' => 'Knit', 'uom' => 'Kg'])->assertCreated()->json('data.id');

        // A draft saved, saved again, then submitted; another draft deleted.
        $lines = fn (int $qty): array => [['kind' => 'FABRIC', 'item_id' => $fabric, 'req_qty' => $qty, 'uom' => 'KG', 'req_date' => '2026-11-20']];
        $pr = $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'lines' => $lines(120)])->assertCreated()->json('data.pr_no');
        $this->as()->putJson("/api/v1/material-procurement/requisitions/{$pr}", ['req_type' => 'DIRECT', 'lines' => $lines(100)])->assertOk();
        $this->as()->postJson("/api/v1/material-procurement/requisitions/{$pr}/submit")->assertOk();
        $gone = $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'lines' => $lines(5)])->assertCreated()->json('data.pr_no');
        $this->as()->deleteJson("/api/v1/material-procurement/requisitions/{$gone}")->assertOk();

        // Quoted, asked to negotiate, revised, approved and handed over; followed up and received.
        $line = $this->as()->getJson("/api/v1/material-procurement/requisitions/{$pr}")->json('data.lines.0.id');
        $quote = fn (float $price): string => $this->as($poly)->postJson("/api/v1/material-procurement/portal/requirements/{$line}/quote", ['quoted_qty' => 100, 'unit_price' => $price, 'currency' => 'USD', 'lead_time_days' => 14, 'expected_delivery' => '2026-11-15'])->assertCreated()->json('data.qt_no');
        $qt = $quote(2.85);
        $qtId = Quotation::query()->where('qt_no', $qt)->value('id');
        $this->as()->postJson("/api/v1/material-procurement/price-review/quotations/{$qtId}/negotiate", ['message' => 'Please revise your quotation and submit your best possible price.'])->assertOk();
        $quote(2.65);
        $this->as()->postJson("/api/v1/material-procurement/price-review/quotations/{$qtId}/approve", ['handover_to' => $merch->id])->assertOk();
        $fu = $this->as($merch)->postJson("/api/v1/material-procurement/assigned-materials/{$line}/followups", ['followup_date' => '2026-10-28', 'status' => 'PARTIALLY_RECEIVED', 'received_qty' => 40])->assertCreated()->json('data.followups.0.id');
        $grn = $this->as($merch)->postJson("/api/v1/material-procurement/followups/{$fu}/grn", ['unit_company_id' => $this->delta->id, 'store_id' => $this->store])->assertCreated()->json('data.grn_no');

        // A bn user's entry is still English.
        $this->owner->update(['locale' => 'bn']);
        $this->as()->patchJson("/api/v1/erp/material-fabrics/{$fabric}", ['name' => 'Cotton Jersey Knit'])->assertOk();

        $rows = $this->as()->getJson('/api/v1/material-procurement/procurement-audit-trail')->assertOk()->json('data.rows');
        $this->assertSame([
            ['UPDATE', 'fabric-master', 'FAB-001', 'Updated FAB-001 Cotton Jersey Knit'],
            ['CREATE', 'assigned-materials', $grn, "Raised draft GRN {$grn} for Cotton Jersey ({$pr}), 40"],
            ['CREATE', 'assigned-materials', $pr, "Follow-up on Cotton Jersey ({$pr}): PARTIALLY_RECEIVED, received 40 — total 40/100 KG"],
            ['UPDATE', 'price-review', $qt, "Approved {$qt} (Poly Plan Ltd) for Cotton Jersey on {$pr} at USD 2.65 (revision 2); handed over to {$merch->name}"],
            ['CREATE', 'available-requirements', $qt, "Poly Plan Ltd submitted {$qt} revision 2 for Cotton Jersey on {$pr}: USD 2.65 × 100, 14 days"],
            ['UPDATE', 'price-review', $qt, "Requested negotiation on {$qt} from Poly Plan Ltd: Please revise your quotation and submit your best possible price."],
            ['CREATE', 'available-requirements', $qt, "Poly Plan Ltd submitted {$qt} revision 1 for Cotton Jersey on {$pr}: USD 2.85 × 100, 14 days"],
            ['DELETE', 'my-requisitions', $gone, "Deleted draft {$gone}"],
            ['CREATE', 'create-requisition', $gone, "Saved draft {$gone} (Direct) — 1 line(s): Cotton Jersey 5 KG"],
            ['UPDATE', 'my-requisitions', $pr, "Submitted {$pr} to all suppliers"],
            ['UPDATE', 'create-requisition', $pr, "Updated draft {$pr} (Direct) — 1 line(s): Cotton Jersey 100 KG"],
            ['CREATE', 'create-requisition', $pr, "Saved draft {$pr} (Direct) — 1 line(s): Cotton Jersey 120 KG"],
            ['CREATE', 'fabric-master', 'FAB-001', 'Added FAB-001 Cotton Jersey'],
            ['UPDATE', 'supplier-portal-access', 'SUP-002', 'Enabled supplier portal login desk@polyplan.test (Poly Plan Ltd)'],
            ['UPDATE', 'supplier-portal-access', 'SUP-002', 'Disabled supplier portal login desk@polyplan.test (Poly Plan Ltd)'],
            ['CREATE', 'supplier-portal-access', 'SUP-002', 'Created supplier portal login desk@polyplan.test for Poly Plan Ltd'],
            ['UPDATE', 'supplier-master', 'SUP-002', 'Updated SUP-002 Poly Plan Ltd'],
            ['CREATE', 'supplier-master', 'SUP-002', 'Added SUP-002 Poly Plan Ltd'],
        ], array_map(fn (array $r): array => [$r['action'], $r['screen'], $r['reference'], $r['summary']], $rows));
        $this->assertSame([$this->owner->name, $merch->name, $merch->name, $this->owner->name, 'Poly Desk'], array_column(array_slice($rows, 0, 5), 'user'));

        // Permanent: never edited, never deleted.
        $entry = AuditEntry::query()->firstOrFail();
        try {
            $entry->update(['summary' => 'changed']);
            $this->fail('An audit entry was edited.');
        } catch (RuntimeException $e) {
            $this->assertSame('Audit trail entries are permanent and cannot be edited.', $e->getMessage());
        }
        $this->expectExceptionMessage('Audit trail entries are permanent and cannot be deleted.');
        try {
            $entry->delete();
        } finally {
            // Who reads it: the audit permission, not every procurement user.
            $this->as($merch)->getJson('/api/v1/material-procurement/procurement-audit-trail')->assertForbidden();
            $this->as(TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test'))->getJson('/api/v1/material-procurement/procurement-audit-trail')->assertOk();
        }
    }

    public function test_reviewers_hear_of_quotations_and_notifications_lists_only_the_modules_own(): void
    {
        $supplier = $this->as()->postJson('/api/v1/erp/material-suppliers', ['code' => 'SUP-002', 'name' => 'Poly Plan Ltd', 'supplier_type' => 'LOCAL_NON_EPZ'])->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/material-procurement/supplier-logins', ['supplier_id' => $supplier, 'contact_name' => 'Poly Desk', 'email' => 'desk@polyplan.test', 'password' => 'Quote-Ready-2026!'])->assertCreated();
        $poly = User::query()->where('email', 'desk@polyplan.test')->firstOrFail();
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $fabric = $this->as()->postJson('/api/v1/erp/material-fabrics', ['item_code' => 'FAB-001', 'name' => 'Cotton Jersey', 'category' => 'Knit', 'uom' => 'Kg'])->assertCreated()->json('data.id');
        $pr = $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'submit' => true, 'lines' => [['kind' => 'FABRIC', 'item_id' => $fabric, 'req_qty' => 100, 'uom' => 'KG', 'req_date' => '2026-11-20']]])->assertCreated()->json('data');
        $line = $pr['lines'][0]['id'];
        foreach ([2.85, 2.70] as $price) {
            $this->as($poly)->postJson("/api/v1/material-procurement/portal/requirements/{$line}/quote", ['quoted_qty' => 100, 'unit_price' => $price, 'currency' => 'USD', 'lead_time_days' => 14, 'expected_delivery' => '2026-11-15'])->assertCreated();
        }
        $qt = Quotation::query()->value('qt_no');
        // Submitted at once: one entry that says so.
        $this->assertSame("Created {$pr['pr_no']} (Direct) — 1 line(s): Cotton Jersey 100 KG", AuditEntry::query()->where('screen', 'create-requisition')->value('summary'));

        // The owner reviews prices; the storekeeper does not.
        $mine = fn (User $u): array => Notification::query()->withoutGlobalScopes()->where('user_id', $u->id)->where('event_type', 'like', 'MP\_QUOTATION\_RE%')->orderBy('created_at')->pluck('body')->all();
        $this->assertSame(["Poly Plan Ltd quoted Cotton Jersey on {$pr['pr_no']} ({$qt}).", "Poly Plan Ltd sent revision 2 for Cotton Jersey on {$pr['pr_no']} ({$qt})."], $mine($this->owner));
        $this->assertSame([], $mine($keeper));

        // Notifications: the module's own, newest first; another module's stays out and stays unread.
        Notification::query()->create(['company_id' => $this->delta->id, 'user_id' => $this->owner->id, 'event_type' => 'MAINTENANCE_DUE', 'title' => 'Maintenance due', 'body' => 'Knitting M/C 4']);
        $rows = $this->as()->getJson('/api/v1/material-procurement/procurement-notifications')->assertOk()->json('data.rows');
        $this->assertSame(['MP_QUOTATION_REVISED', 'MP_QUOTATION_RECEIVED'], array_column($rows, 'event'));
        $this->assertSame(['Supplier Submitted a Revised Quotation', '/material-procurement/price-review', false], [$rows[0]['title'], $rows[0]['url'], $rows[0]['read']]);
        $this->as()->postJson("/api/v1/material-procurement/procurement-notifications/{$rows[1]['id']}/read")->assertOk();
        $this->assertSame([false, true], array_column($this->as()->getJson('/api/v1/material-procurement/procurement-notifications')->json('data.rows'), 'read'));
        $this->as()->postJson('/api/v1/material-procurement/procurement-notifications/read-all')->assertOk()->assertJsonPath('data.marked', 1);
        $this->assertNull(Notification::query()->withoutGlobalScopes()->where('event_type', 'MAINTENANCE_DUE')->value('read_at'));
        // Someone else's notification is not the caller's to read.
        $this->as($keeper)->postJson("/api/v1/material-procurement/procurement-notifications/{$rows[0]['id']}/read")->assertNotFound();

        // The supplier's portal login has its own screens.
        $this->as($poly)->getJson('/api/v1/material-procurement/procurement-notifications')->assertForbidden();
        $this->as($keeper)->getJson('/api/v1/material-procurement/procurement-notifications')->assertOk()->assertJsonPath('data.rows', []);
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
