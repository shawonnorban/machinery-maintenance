<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\StyleBom;
use App\Modules\Merchandise\Models\StyleBomLine;
use App\Modules\Merchandise\Models\StyleBomVersion;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Trims & Accessories → Dummy Order Accessories Indent (AnnotechERP 06/06):
 * the same requisition raised against any order not cancelled — one not yet
 * confirmed too — numbered PRQ-DUM-…, listed apart from the regular indents
 * and never blocking them; the Order BOM read with its rates and remarks; an
 * approved dummy indent ordered against like any other.
 */
class MerchandiseDummyIndentTest extends TestCase
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

    public function test_a_dummy_order_indent_is_raised_on_an_unconfirmed_order_and_kept_apart(): void
    {
        $order = $this->order();
        $button = MaterialItem::query()->create(['kind' => 'TRIMS', 'item_code' => 'TR-BTN-01', 'name' => 'Button 18L', 'category' => 'Button', 'uom' => 'Pcs', 'active' => true])->id;
        $bom = StyleBom::query()->create(['style_id' => $order['style_id'], 'bom_no' => 'BOM-D1']);
        $v = StyleBomVersion::query()->create(['bom_id' => $bom->id, 'version' => 1, 'status' => StyleBomVersion::APPROVED, 'order_qty' => 100, 'addl_pct' => 0, 'approved_at' => now()]);
        StyleBomLine::query()->create(['version_id' => $v->id, 'item_name' => 'Button 18L', 'category' => 'TRIMS', 'item_code' => 'TR-BTN-01', 'cons_type' => 'FIXED', 'consumption' => 2, 'cons_unit' => 'PCS', 'per_gmt' => 1, 'purchase_unit' => 'PCS', 'conversion' => 1, 'apply_addl' => false, 'rate' => 0.45, 'remarks' => '4-hole', 'sort_order' => 1]);

        // A draft order is no regular indent's, but a dummy indent's.
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/acc-requisitions/options')->assertOk()->json('data.orders'));
        $this->assertSame([[$order['order_no'], 'BOM-D1 V1']], array_map(fn ($o) => [$o['order_no'], $o['bom']], $this->as()->getJson('/api/v1/merchandise/acc-requisitions/options?dummy=1')->json('data.orders')));
        $sheet = $this->as()->getJson("/api/v1/merchandise/acc-requisitions/bom?order_id={$order['id']}")->json('data.lines.0');
        $this->assertEquals([200, 0.45, '4-hole'], [$sheet['required_qty'], $sheet['rate'], $sheet['bom_remarks']]);

        $line = ['item_id' => $button, 'required_qty' => 200];
        $dummy = $this->as()->postJson('/api/v1/merchandise/acc-requisitions', ['dummy' => true, 'req_date' => '2026-10-17', 'order_id' => $order['id'], 'lines' => [$line]])->assertCreated()->json('data');
        $this->assertSame(['PRQ-DUM-2026100001', true, 'DRAFT'], [$dummy['req_no'], $dummy['dummy'], $dummy['status']]);
        // A second dummy indent for the same button is refused, as a regular one would be.
        $this->as()->postJson('/api/v1/merchandise/acc-requisitions', ['dummy' => true, 'req_date' => '2026-10-17', 'order_id' => $order['id'], 'lines' => [$line]])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.item_id']);

        $this->assertSame(['PRQ-DUM-2026100001'], array_column($this->as()->getJson('/api/v1/merchandise/acc-requisitions?dummy=1')->assertOk()->json('data'), 'req_no'));
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/acc-requisitions')->json('data'));
        $this->assertSame(['PRQ-DUM-2026100001'], array_column($this->as()->getJson('/api/v1/merchandise/acc-requisitions/options?dummy=1')->json('data.requisitions'), 'value'));

        // Confirmed, the order takes its regular indent too — the dummy one does not block it.
        Order::query()->whereKey($order['id'])->update(['status' => 'CONFIRMED']);
        $regular = $this->as()->postJson('/api/v1/merchandise/acc-requisitions', ['req_date' => '2026-10-18', 'order_id' => $order['id'], 'lines' => [$line]])->assertCreated()->json('data');
        $this->assertSame(['PRQ-ACC-2026100001', false], [$regular['req_no'], $regular['dummy']]);
        // Edited, each is still kept apart.
        $this->as()->putJson('/api/v1/merchandise/acc-requisitions/PRQ-DUM-2026100001', ['req_date' => '2026-10-17', 'order_id' => $order['id'], 'remarks' => 'Pre-booking', 'lines' => [$line]])->assertOk()->assertJsonPath('data.dummy', true);

        // Approved, a dummy indent is ordered against like any other.
        $this->as()->postJson('/api/v1/merchandise/acc-requisitions/PRQ-DUM-2026100001/submit')->assertOk();
        $this->as()->postJson('/api/v1/merchandise/acc-requisitions/PRQ-DUM-2026100001/approve')->assertOk();
        $this->assertSame('PRQ-DUM-2026100001', $this->as()->getJson('/api/v1/merchandise/acc-purchase-orders/requisitions/PRQ-DUM-2026100001')->assertOk()->json('data.req_no'));
        $this->assertContains('PRQ-DUM-2026100001', array_column($this->as()->getJson('/api/v1/merchandise/acc-purchase-orders/options')->json('data.requisitions'), 'label'));
    }

    /** A draft order of 100 pcs for style 637PK. */
    private function order(): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Spolka Akcyjna Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 100, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data');
        Order::query()->whereKey($order['id'])->update(['status' => 'DRAFT']);

        return $order + ['style_id' => $style];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
