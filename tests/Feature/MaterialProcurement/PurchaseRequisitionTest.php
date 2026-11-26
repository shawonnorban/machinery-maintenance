<?php

declare(strict_types=1);

namespace Tests\Feature\MaterialProcurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\PurchaseRequisitionLine;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Material Procurement → Purchase Requisition (AnnotechERP 08/03): a
 * requisition by confirmed order carries the order's buyer, style, quantity
 * and delivery date; a direct one carries only its own requirement. Each
 * line is a master item of its own tab, its quantity in a unit of measure
 * and its date, checked line by line as Brickland words it. A draft is
 * edited, submitted to all suppliers or deleted; a submitted one is locked
 * and never deleted.
 */
class PurchaseRequisitionTest extends TestCase
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

    public function test_a_requisition_by_order_is_checked_line_by_line_saved_as_a_draft_edited_and_submitted(): void
    {
        [$order, $orderNo] = $this->confirmedOrder();
        [$fabric, $trim] = $this->items();

        $options = $this->as()->getJson('/api/v1/material-procurement/requisitions/options')->assertOk()->json('data');
        $this->assertSame('PR-'.now()->format('Y').'-00001', $options['pr_no']);
        $this->assertSame([$orderNo], array_column($options['orders'], 'order_no'));
        $loaded = $options['orders'][0];
        $this->assertSame(['LPP', 'SS27-TEE01', 1000, 'PCS', '2027-01-15', 'Ms. Anna', 'CONFIRMED'], [$loaded['buyer'], $loaded['style_no'], $loaded['order_qty'], $loaded['order_unit'], $loaded['delivery_date'], $loaded['buyer_contact'], $loaded['order_status']]);
        $this->assertSame(['FAB-001 — Cotton Jersey · White · 160 GSM'], array_column($options['items']['FABRIC'], 'label'));
        $this->assertSame(['KG', '160 GSM combed'], [$options['items']['FABRIC'][0]['uom'], $options['items']['FABRIC'][0]['specification']]);
        $this->assertSame(['ACC-001 — Main Label · White · 50 x 20 mm'], array_column($options['items']['TRIMS'], 'label'));
        $this->assertSame($this->owner->name, $options['raiser']['name']);

        // As Brickland words each missing piece.
        $url = '/api/v1/material-procurement/requisitions';
        $this->as()->postJson($url, ['req_type' => 'BY_ORDER'])->assertUnprocessable()
            ->assertJsonPath('errors.order_id.0', 'Select the confirmed order this requisition is for.')->assertJsonPath('errors.lines.0', 'Add at least one material line.');
        $line = ['kind' => 'FABRIC', 'item_id' => $fabric, 'req_qty' => 120, 'uom' => 'kg', 'req_date' => '2026-11-20', 'specification' => '160 GSM combed'];
        $errors = $this->as()->postJson($url, ['req_type' => 'BY_ORDER', 'order_id' => $order, 'lines' => [
            ['req_qty' => 0] + $line, ['kind' => 'TRIMS', 'item_id' => $fabric, 'req_qty' => 'x', 'uom' => 'BOXES OF TEN', 'req_date' => null],
        ]])->assertUnprocessable()->json('errors');
        $this->assertSame('Line 1: Required qty must be a number greater than zero.', $errors['lines.0.req_qty'][0]);
        // The fabric is not an accessory; numbering runs within each tab.
        $this->assertSame('Line 1: Select an item.', $errors['lines.1.item_id'][0]);
        $this->assertSame('Line 1: BOXES OF TEN is not a unit of measure.', $errors['lines.1.uom'][0]);
        $this->assertSame('Line 1: Requirement date is required.', $errors['lines.1.req_date'][0]);

        $draft = $this->as()->postJson($url, ['req_type' => 'BY_ORDER', 'order_id' => $order, 'remarks' => 'For bulk cutting', 'lines' => [$line]])->assertCreated()->json('data');
        $this->assertSame(['PR-'.now()->format('Y').'-00001', 'BY_ORDER', 'DRAFT', true, $this->owner->name, 1], [$draft['pr_no'], $draft['req_type'], $draft['status'], $draft['editable'], $draft['created_by_name'], $draft['lines_count']]);
        $this->assertEquals(['KG', 120, 'OPEN_FOR_QUOTATION', 0, 'Fabric · Knit · White · 160 GSM'], [$draft['lines'][0]['uom'], $draft['lines'][0]['req_qty'], $draft['lines'][0]['status'], $draft['lines'][0]['quotations'], $draft['lines'][0]['item_detail']]);
        $this->assertSame([$orderNo, 'LPP', '2027-01-15'], [$draft['order']['order_no'], $draft['order']['buyer'], $draft['order']['delivery_date']]);

        // Edit draft: an accessories line added, then submitted in the same save.
        $no = $draft['pr_no'];
        $edited = $this->as()->putJson("{$url}/{$no}", ['order_id' => $order, 'lines' => [$line, ['kind' => 'TRIMS', 'item_id' => $trim, 'req_qty' => 5000, 'uom' => 'PCS', 'req_date' => '2026-11-25']], 'submit' => true])
            ->assertOk()->json('data');
        $this->assertSame(['SUBMITTED', false, ['FABRIC', 'TRIMS']], [$edited['status'], $edited['editable'], array_column($edited['lines'], 'kind')]);
        $this->assertNotNull($edited['submitted_at']);
        $this->assertSame(2, PurchaseRequisitionLine::query()->count());

        // Submitted: locked and never deleted.
        $this->as()->putJson("{$url}/{$no}", ['order_id' => $order, 'lines' => [$line]])->assertUnprocessable()->assertJsonPath('errors.status.0', 'Only a draft requisition can be changed.');
        $this->as()->postJson("{$url}/{$no}/submit")->assertUnprocessable()->assertJsonPath('errors.status.0', 'Only a draft requisition can be changed.');
        $this->as()->deleteJson("{$url}/{$no}")->assertUnprocessable()->assertJsonPath('errors.status.0', 'Submitted requisitions can never be deleted.');

        // An order that is no longer confirmed cannot be picked.
        Order::query()->whereKey($order)->update(['status' => 'CANCELLED']);
        $this->as()->postJson($url, ['req_type' => 'BY_ORDER', 'order_id' => $order, 'lines' => [$line]])->assertUnprocessable()->assertJsonValidationErrors('order_id');
    }

    public function test_a_direct_requisition_carries_no_order_and_a_draft_is_submitted_or_deleted(): void
    {
        [$order] = $this->confirmedOrder();
        [$fabric] = $this->items();
        $url = '/api/v1/material-procurement/requisitions';
        $line = ['kind' => 'FABRIC', 'item_id' => $fabric, 'req_qty' => 50, 'uom' => 'KG', 'req_date' => '2026-11-20'];

        // A direct requisition ignores any order sent with it.
        $one = $this->as()->postJson($url, ['req_type' => 'DIRECT', 'order_id' => $order, 'lines' => [$line]])->assertCreated()->json('data');
        $this->assertSame(['DIRECT', null, null], [$one['req_type'], $one['order_id'], $one['order']]);
        $two = $this->as()->postJson($url, ['req_type' => 'DIRECT', 'lines' => [$line]])->assertCreated()->json('data.pr_no');

        $this->as()->postJson("{$url}/{$one['pr_no']}/submit")->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->as()->deleteJson("{$url}/{$two}")->assertOk()->assertJsonPath('data.deleted', $two);
        $this->assertSame([$one['pr_no']], array_column($this->as()->getJson($url)->assertOk()->json('data'), 'pr_no'));
        $this->as()->getJson("{$url}/{$two}")->assertNotFound();
        // A deleted draft was never sent anywhere, so the last number is free again.
        $this->assertSame('PR-'.now()->format('Y').'-00002', $this->as()->postJson($url, ['req_type' => 'DIRECT', 'lines' => [$line]])->assertCreated()->json('data.pr_no'));
        $this->as()->postJson($url, ['lines' => [$line]])->assertUnprocessable()->assertJsonPath('errors.req_type.0', 'Choose Requisition by Order or Direct Requisition.');

        // The storekeeper follows requisitions but does not raise them; a technician sees none.
        $storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'store@delta.test');
        $this->as($storekeeper)->getJson($url)->assertOk();
        $this->as($storekeeper)->postJson($url, ['req_type' => 'DIRECT', 'lines' => [$line]])->assertForbidden();
        $this->as(TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test'))->getJson($url)->assertForbidden();
    }

    /** @return array{string, string} a fabric and an accessory from the master */
    private function items(): array
    {
        $fabric = $this->as()->postJson('/api/v1/erp/material-fabrics', ['item_code' => 'FAB-001', 'name' => 'Cotton Jersey', 'category' => 'Knit', 'gsm' => '160', 'width' => '60"', 'colour' => 'White', 'uom' => 'Kg', 'specification' => '160 GSM combed'])
            ->assertCreated()->json('data.id');
        $trim = $this->as()->postJson('/api/v1/erp/material-trims', ['item_code' => 'ACC-001', 'name' => 'Main Label', 'category' => 'Label', 'colour' => 'White', 'size' => '50 x 20 mm', 'uom' => 'Pcs'])->assertCreated()->json('data.id');

        return [$fabric, $trim];
    }

    /** @return array{string, string} a confirmed order of LPP's SS27-TEE01, and its number */
    private function confirmedOrder(): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '616'], ['name' => 'Poland', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'contact_person' => 'Ms. Anna', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => 'SS27-TEE01', 'description' => 'Summer Set', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 1000, 'unit' => 'PCS', 'ship_date' => '2027-01-15', 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');
        // A second order, still a draft, is not offered.
        $draft = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '530', 'style_id' => $style, 'total_qty' => 500, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');
        Order::query()->whereKey($order)->update(['status' => 'CONFIRMED']);
        Order::query()->whereKey($draft)->update(['status' => 'DRAFT']);

        return [$order, Order::query()->whereKey($order)->value('order_no')];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
