<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Settings\Models\Currency;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Production (AnnotechERP 15/04), the floor's yarn
 * paperwork: General Yarn Issue Indent and Yarn Return to Yarn Store write
 * the same requisitions and return challans the Yarn Store (15/03) works —
 * the floor raises, closes, sends back and cancels; issuing yarn and
 * accepting a return stay the store's — and Supplier Yarn Replacement
 * Indent: a claim on a spinner, lots upper-cased, Draft → Submitted →
 * Issued, and Cancelled.
 */
class KnittingYarnIndentTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $floor;

    private string $store;

    private string $cotton;

    private string $count;

    private string $spinner;

    private string $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Yarn Store', 'code' => 'ST-YRN', 'active' => true])->id;
        $this->cotton = FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton', 'active' => true])->id;
        $this->count = KnitYarnCount::query()->create(['count_no' => 'YC-30-1', 'count_system' => 'Ne', 'description' => '30/1', 'count_value' => 30, 'is_single' => true, 'display_order' => 1, 'active' => true])->id;
        $this->spinner = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Square Spinning', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000060', 'job_no' => '7701', 'style_no' => '770MR', 'buyer_id' => $buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
    }

    public function test_floor_indent_and_return_share_the_store_paper(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $this->floor = $this->floorUser();
        $usd = Currency::query()->create(['company_id' => $this->delta->id, 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 119.5, 'active' => true])->id;
        $yarn = ['composition_id' => $this->cotton, 'yarn_count_id' => $this->count, 'colour' => 'Natural'];
        $ob = $this->as()->postJson('/api/v1/knitting/ystore/obs', ['ob_date' => now()->startOfMonth()->toDateString(), 'store_id' => $this->store, 'currency_id' => $usd, 'lines' => [
            $yarn + ['brand' => 'Square', 'source' => 'LOCAL', 'lot_no' => 'L-1', 'supplier_id' => $this->spinner, 'total_qty' => 1000, 'cost_price' => 3],
        ]])->assertCreated()->json('data.ob_no');
        $this->as()->postJson("/api/v1/knitting/ystore/obs/{$ob}/finalize")->assertOk();
        $lot = YarnLot::query()->where('lot_no', 'L-1')->value('id');

        // The floor reads the requisition options: the store's lots and each order's style.
        $options = $this->as($this->floor)->getJson('/api/v1/knitting/ystore/issue-reqs/options')->assertOk()->json('data');
        $this->assertSame([['L-1', 'Square']], array_map(fn ($l) => [$l['lot_no'], $l['brand']], $options['lots']));
        $this->assertSame('770MR', collect($options['orders'])->firstWhere('value', $this->order)['style']);

        // The floor raises the requisition; the store's list carries its Style No and Order No.
        $req = ['req_date' => $today, 'req_type' => 'KNIT_DEVELOPMENT', 'store_id' => $this->store, 'order_id' => $this->order, 'program_no' => 'TRIAL-7',
            'lines' => [$yarn + ['brand' => 'Square', 'lot_no' => 'L-1', 'uom' => 'kg', 'req_qty' => 40]]];
        $no = $this->as($this->floor)->postJson('/api/v1/knitting/ystore/issue-reqs', $req)->assertCreated()->json('data.req_no');
        $this->assertSame("YIR-{$ym}0001", $no);
        $row = $this->as($this->floor)->getJson('/api/v1/knitting/ystore/issue-reqs')->assertOk()->json('data.0');
        $this->assertEquals(['770MR', 'LPP-2026-000060', 40.0, 0.0], [$row['style_no'], $row['order_no'], $row['req_qty'], $row['issued_qty']]);

        // Issuing is the store's: the floor sees neither the issue source nor the issue desk.
        $this->as($this->floor)->getJson("/api/v1/knitting/ystore/issue-reqs/{$no}/source")->assertForbidden();
        $line = $this->as()->getJson("/api/v1/knitting/ystore/issue-reqs/{$no}/source")->assertOk()->json('data.lines.0.id');
        $issue = ['issue_date' => $today, 'req_no' => $no, 'lines' => [['req_line_id' => $line, 'lot_id' => $lot, 'qty' => 25]]];
        $this->as($this->floor)->postJson('/api/v1/knitting/ystore/issues', $issue)->assertForbidden();
        $this->as()->postJson('/api/v1/knitting/ystore/issues', $issue)->assertCreated();

        // The floor sends back what was left at the machine; only the store accepts it.
        $issueLine = $this->as($this->floor)->getJson("/api/v1/knitting/ystore/issue-returns/source/{$no}")->assertOk()->json('data.issue_lines.0.issue_line_id');
        $ret = ['return_date' => $today, 'req_type' => 'KNIT_DEVELOPMENT', 'store_id' => $this->store, 'req_no' => $no, 'return_by' => 'Floor 1 — Karim',
            'lines' => [['issue_line_id' => $issueLine, 'ret_qty' => 5, 'recv_qty' => 5]]];
        $rno = $this->as($this->floor)->postJson('/api/v1/knitting/ystore/issue-returns', $ret)->assertCreated()->json('data.return_no');
        $this->as($this->floor)->postJson("/api/v1/knitting/ystore/issue-returns/{$rno}/issue")->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $this->as($this->floor)->postJson("/api/v1/knitting/ystore/issue-returns/{$rno}/accept")->assertForbidden();
        $this->as()->postJson("/api/v1/knitting/ystore/issue-returns/{$rno}/accept")->assertOk()->assertJsonPath('data.status', 'ACCEPTED');

        // The floor closes what it no longer needs.
        $this->as($this->floor)->postJson("/api/v1/knitting/ystore/issue-reqs/{$no}/close")->assertOk()->assertJsonPath('data.status', 'CLOSED');
    }

    public function test_replacement_claim_drafted_submitted_issued_and_cancelled(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $options = $this->as()->getJson('/api/v1/knitting/prod/replace-reqs/options')->assertOk()->json('data');
        $this->assertContains('CLAIM_YARN_TEST', $options['return_types']);

        $line = ['composition_id' => $this->cotton, 'yarn_count_id' => $this->count, 'colour_group' => 'Natural', 'brand' => 'Square', 'lot_no' => ' sq-3011 ', 'qty' => 120, 'uom' => 'kg'];
        $c = ['req_date' => $today, 'store_id' => $this->store, 'supplier_id' => $this->spinner, 'return_type' => 'CLAIM_YARN_TEST', 'lc_no' => 'LC-0045/26', 'lot_ref' => 'sq-3011',
            'remarks' => 'Neps over the limit on the lab test', 'lines' => [$line]];
        $this->as()->postJson('/api/v1/knitting/prod/replace-reqs', ['lines' => []] + $c)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A claim with no yarn on it asks the spinner to replace nothing.');
        $this->as()->postJson('/api/v1/knitting/prod/replace-reqs', ['return_type' => null] + $c)->assertUnprocessable()->assertJsonPath('errors.return_type.0', 'Return Type is required.');
        $this->as()->postJson('/api/v1/knitting/prod/replace-reqs', ['lines' => [['qty' => 0] + $line]] + $c)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => 'Qty must be more than nothing.']);
        $made = $this->as()->postJson('/api/v1/knitting/prod/replace-reqs', $c)->assertCreated()->json('data');
        $no = "YRR-{$ym}0001";
        $this->assertEquals([$no, 'DRAFT', 'SQ-3011', 'SQ-3011', '100% Cotton 30/1', 'Square Spinning', 1, 120.0],
            [$made['req_no'], $made['status'], $made['lot_ref'], $made['lines'][0]['lot_no'], $made['lines'][0]['yarn'], $made['supplier'], count($made['lines']), $made['qty']]);

        // Two lots in the order sent; then submitted, and the claim is fixed.
        $two = ['lines' => [$line, ['lot_no' => 'sq-3012', 'qty' => 30] + $line]] + $c;
        $this->assertSame(['SQ-3011', 'SQ-3012'], array_column($this->as()->putJson("/api/v1/knitting/prod/replace-reqs/{$no}", $two)->assertOk()->json('data.lines'), 'lot_no'));
        $this->as()->postJson("/api/v1/knitting/prod/replace-reqs/{$no}/issue")->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->postJson("/api/v1/knitting/prod/replace-reqs/{$no}/submit")->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->as()->putJson("/api/v1/knitting/prod/replace-reqs/{$no}", $c)->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$no} is Submitted — only a draft can be rewritten.");
        $issued = $this->as()->postJson("/api/v1/knitting/prod/replace-reqs/{$no}/issue")->assertOk()->json('data');
        $this->assertEquals(['ISSUED', 2, 150.0], [$issued['status'], $issued['lines'], $issued['qty']]);
        $this->as()->postJson("/api/v1/knitting/prod/replace-reqs/{$no}/cancel")->assertUnprocessable()->assertJsonValidationErrors('status');

        // A second claim, cancelled while still a draft.
        $two = $this->as()->postJson('/api/v1/knitting/prod/replace-reqs', ['return_type' => 'SHORT_SUPPLY'] + $c)->assertCreated()->json('data.req_no');
        $this->as()->postJson("/api/v1/knitting/prod/replace-reqs/{$two}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertEquals([['SHORT_SUPPLY', 'CANCELLED'], ['CLAIM_YARN_TEST', 'ISSUED']],
            array_map(fn ($r) => [$r['return_type'], $r['status']], $this->as()->getJson('/api/v1/knitting/prod/replace-reqs')->json('data')));
    }

    /** A knitting floor login: production rights, none of the Yarn Store's. */
    private function floorUser(): User
    {
        $role = $this->as()->postJson('/api/v1/roles', ['code' => 'KNIT_FLOOR', 'name' => 'Knitting Floor', 'permissions' => ['knitting.production.view', 'knitting.production.manage']])
            ->assertCreated()->json('data.id');
        $user = TenantFixture::user($this->delta, 'LINE_CHIEF', 'floor@delta.test');
        UserRole::withoutGlobalScope(TenantScope::class)->create(['company_id' => $this->delta->id, 'user_id' => $user->id, 'role_id' => Role::query()->findOrFail($role)->id]);
        app(PermissionResolver::class)->flush();

        return $user;
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
