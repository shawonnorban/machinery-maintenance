<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\DyeingLossBand;
use App\Modules\Merchandise\Models\FabricBookingLine;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Fabric Booking → Additional Fabric Booking Entry (AnnotechERP 06/11): a
 * requisition keyed by its header, numbered BK-ADD-00001 and revised on each
 * update; the Booking Items panel adding and adjusting its fabric lines, the
 * process loss defaulting from the dyeing norms; a bulk booking's lines only
 * shown there; an approved requisition joining the Approved list.
 */
class MerchandiseFabricRequisitionTest extends TestCase
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

    public function test_a_requisition_is_numbered_revised_and_its_fabric_lines_kept_on_the_booking_items_panel(): void
    {
        [$style, $office, $order] = $this->order();
        $sj = $this->as()->postJson('/api/v1/erp/fabric-types', ['code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'SJ', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'sort_order' => 1])->assertCreated()->json('data.id');
        $dark = DyeColourGroup::query()->create(['code' => 'DK', 'name' => 'Dark', 'active' => true])->id;
        $black = $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'SOLID', 'name' => 'Black', 'code' => 'COL-BLK'])->assertCreated()->json('data.id');
        Colour::query()->whereKey($black)->update(['colour_group_id' => $dark]);
        $cotton = FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton', 'active' => true])->id;
        DyeingLossBand::query()->create(['fiber_composition_id' => $cotton, 'fabric_type_id' => null, 'color_group' => 'DARK', 'weight_low' => 1, 'weight_high' => 200, 'loss_percent' => 12, 'active' => true]);
        DyeingLossBand::query()->create(['fiber_composition_id' => $cotton, 'fabric_type_id' => $sj, 'color_group' => 'DARK', 'weight_low' => 140, 'weight_high' => 180, 'loss_percent' => 9.5, 'active' => true]);

        $options = $this->as()->getJson('/api/v1/merchandise/fabric-requisitions/options')->assertOk()->json('data');
        $this->assertSame(['Delta Apparels Ltd', '637PK — Hoodie'], [$options['company'], $options['styles'][0]['label']]);

        $this->as()->postJson('/api/v1/merchandise/fabric-requisitions?type=ADDITIONAL', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_date' => 'Booking Date is required.']);
        $other = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $this->buyerId, 'buyer_office_id' => $office, 'style_ref' => '900XL', 'description' => 'Tee', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$this->sizeId]]]])->json('data.id');
        $this->as()->postJson('/api/v1/merchandise/fabric-requisitions?type=ADDITIONAL', ['booking_date' => '2026-10-16', 'style_id' => $other, 'order_id' => $order['id']])->assertUnprocessable()
            ->assertJsonValidationErrors(['order_id' => 'That job/work style is not of the style picked.']);

        // The order names the style and the buyer account follows it.
        $saved = $this->as()->postJson('/api/v1/merchandise/fabric-requisitions?type=ADDITIONAL', ['booking_date' => '2026-10-16', 'order_id' => $order['id'], 'ref_no' => ' R-77 ', 'send_mail' => true])->assertCreated()->json('data');
        $this->assertSame(['BK-ADD-00001', 0, 'ADDITIONAL', 'DRAFT', $style, $office, 'R-77', true, true], [$saved['booking_no'], $saved['revision'], $saved['booking_type'], $saved['status'], $saved['style_id'], $saved['buyer_office_id'], $saved['ref_no'], $saved['send_mail'], $saved['active']]);

        $updated = $this->as()->putJson('/api/v1/merchandise/fabric-requisitions/BK-ADD-00001', ['booking_date' => '2026-10-16', 'order_id' => $order['id'], 'sp_no' => 'SP-9', 'work_style_no' => 'WS-1', 'status' => 'SUBMITTED'])->assertOk()->json('data');
        $this->assertSame([1, 'SP-9', 'SUBMITTED'], [$updated['revision'], $updated['sp_no'], $updated['status']]);
        $records = $this->as()->getJson('/api/v1/merchandise/fabric-requisitions?type=ADDITIONAL')->assertOk()->json('data');
        $this->assertSame([['BK-ADD-00001', 1, 'WS-1', $order['order_no'], 'LPP SPOLKA AKCYJNA OFFICE', true, 'SUBMITTED']], array_map(fn ($r) => [$r['booking_no'], $r['revision'], $r['work_style'], $r['order_no'], $r['buyer_ac'], $r['active'], $r['status']], $records));
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/fabric-requisitions?type=BUFFER')->json('data'));

        // Booking Items: a line needs its fabric and quantity.
        $this->as()->postJson('/api/v1/merchandise/fabric-booking-items/BK-ADD-00001', ['qty' => 10])->assertUnprocessable()
            ->assertJsonValidationErrors(['fabric_type_id' => 'Fabric Type is required.']);
        $this->as()->postJson('/api/v1/merchandise/fabric-booking-items/BK-ADD-00001', ['fabric_type_id' => $sj])->assertUnprocessable()
            ->assertJsonValidationErrors(['qty' => 'Qty is required.']);
        // Black is Dark: Single Jersey at 160 GSM takes its own band (9.5), at 190 the band for every fabric (12).
        $lines = $this->as()->postJson('/api/v1/merchandise/fabric-booking-items/BK-ADD-00001', ['fabric_type_id' => $sj, 'colour_id' => $black, 'gsm' => 160, 'dia' => 72, 'qty' => 250, 'rate' => 4.2])->assertCreated()->json('data');
        $this->assertEquals([['Single Jersey', 'Black', 160, 72, 250, 'KG', 4.2, 9.5]], array_map(fn ($l) => [$l['fabric'], $l['colour_name'], $l['gsm'], $l['dia'], $l['qty'], $l['uom'], $l['rate'], $l['process_loss']], $lines));
        $lines = $this->as()->postJson('/api/v1/merchandise/fabric-booking-items/BK-ADD-00001', ['fabric_type_id' => $sj, 'colour_id' => $black, 'colour_name' => 'Jet Black', 'gsm' => 190, 'qty' => 40, 'uom' => 'YDS'])->assertCreated()->json('data');
        $this->assertEquals(['Jet Black', 'YDS', 12], [$lines[1]['colour_name'], $lines[1]['uom'], $lines[1]['process_loss']]);

        $bookings = $this->as()->getJson('/api/v1/merchandise/fabric-booking-items')->assertOk()->json('data');
        $this->assertSame([['BK-ADD-00001', 'ADDITIONAL', 2, true]], array_map(fn ($b) => [$b['booking_no'], $b['booking_type'], $b['lines'], $b['editable']], $bookings));

        // Save keeps the adjusted lines; the one left out goes.
        $kept = $this->as()->putJson('/api/v1/merchandise/fabric-booking-items/BK-ADD-00001', ['rows' => [['id' => $lines[0]['id'], 'qty' => 300, 'rate' => 4.5, 'process_loss' => 8, 'gsm' => 160, 'colour_name' => 'Black', 'uom' => 'KG']]])->assertOk()->json('data');
        $this->assertEquals([[300, 4.5, 8]], array_map(fn ($l) => [$l['qty'], $l['rate'], $l['process_loss']], $kept));
        $this->as()->putJson('/api/v1/merchandise/fabric-booking-items/BK-ADD-00001', ['rows' => [['id' => $lines[1]['id'], 'qty' => 5]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['rows' => 'A line was removed elsewhere — reload the booking and try again.']);

        // Approved on its own form, it joins the Approved Fabric Booking List with its SP# and send-mail.
        $this->as()->putJson('/api/v1/merchandise/fabric-requisitions/BK-ADD-00001', ['booking_date' => '2026-10-16', 'order_id' => $order['id'], 'sp_no' => 'SP-9', 'status' => 'APPROVED', 'send_mail' => true])->assertOk();
        $approved = $this->as()->getJson('/api/v1/merchandise/fabric-bookings/approved')->json('data');
        $this->assertSame([['ADDITIONAL', 'BK-ADD-00001', 'SP-9', true, now()->toDateString()]], array_map(fn ($r) => [$r['booking_type'], $r['booking_no'], $r['sp_no'], $r['send_mail'], $r['approved_at']], $approved));
        // Still open to change: only a bulk booking is held by its approval.
        $this->as()->postJson('/api/v1/merchandise/fabric-booking-items/BK-ADD-00001', ['fabric_type_id' => $sj, 'qty' => 1])->assertCreated();
    }

    public function test_a_buffer_order_is_numbered_on_its_own_listed_by_buyer_and_style_and_removed_with_its_lines(): void
    {
        [$style, , $order] = $this->order();
        $sj = $this->as()->postJson('/api/v1/erp/fabric-types', ['code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'SJ', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'sort_order' => 1])->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/merchandise/fabric-requisitions?type=ADDITIONAL', ['booking_date' => '2026-10-16', 'style_id' => $style])->assertCreated();

        $buffer = $this->as()->postJson('/api/v1/merchandise/fabric-requisitions?type=BUFFER', ['booking_date' => '2026-10-16', 'style_id' => $style, 'ref_no' => 'BUF-1'])->assertCreated()->json('data');
        $this->assertSame(['BK-BUF-00001', 'BUFFER'], [$buffer['booking_no'], $buffer['booking_type']]);
        $this->assertSame('BK-BUF-00002', $this->as()->postJson('/api/v1/merchandise/fabric-requisitions?type=BUFFER', ['booking_date' => '2026-10-16', 'order_id' => $order['id']])->assertCreated()->json('data.booking_no'));

        $records = $this->as()->getJson('/api/v1/merchandise/fabric-requisitions?type=BUFFER')->assertOk()->json('data');
        $this->assertSame([['BK-BUF-00002', 'LPP Spolka Akcyjna', '637PK', $order['order_no']], ['BK-BUF-00001', 'LPP Spolka Akcyjna', '637PK', null]], array_map(fn ($r) => [$r['booking_no'], $r['buyer'], $r['style'], $r['order_no']], $records));

        $this->as()->postJson('/api/v1/merchandise/fabric-booking-items/BK-BUF-00001', ['fabric_type_id' => $sj, 'qty' => 12])->assertCreated();
        $this->as()->deleteJson('/api/v1/merchandise/fabric-requisitions/BK-BUF-00001')->assertOk();
        $this->as()->getJson('/api/v1/merchandise/fabric-requisitions/BK-BUF-00001')->assertNotFound();
        $this->assertSame(0, FabricBookingLine::query()->count());
        $this->assertSame(['BK-BUF-00002', 'BK-ADD-00001'], array_column($this->as()->getJson('/api/v1/merchandise/fabric-booking-items')->json('data'), 'booking_no'));
        // A bulk booking is never removed from here.
        $this->as()->deleteJson('/api/v1/merchandise/fabric-requisitions/BFB-00001')->assertNotFound();
    }

    public function test_a_bulk_bookings_lines_are_only_shown_on_the_panel(): void
    {
        [$style, , $order] = $this->order();
        $sj = $this->as()->postJson('/api/v1/erp/fabric-types', ['code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'SJ', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'sort_order' => 1])->assertCreated()->json('data.id');
        $body = GarmentPart::query()->create(['code' => 'BD', 'name' => 'Body', 'active' => true, 'is_body' => true])->id;
        $line = ['fabric_kind' => 'KNIT', 'fabric_type_id' => $sj, 'garment_part_id' => $body, 'dia_unit' => 'INCH', 'dia_type' => 'OPEN', 'consumption' => 1.2, 'cons_unit' => 'KG', 'cons_per' => 'DZ',
            'all_orders' => false, 'gmt_style_id' => $style, 'all_colours' => true, 'all_sizes' => true, 'fab_same_as_gmt' => true, 'cutting_wastage' => 0];
        $this->as()->postJson('/api/v1/merchandise/fabric-bookings?type=BULK', ['booking_date' => '2026-10-15', 'style_id' => $style, 'order_id' => $order['id'], 'currency' => 'USD', 'conv_rate' => 1, 'item_model' => '637PK', 'lines' => [$line]])->assertCreated();

        $panel = $this->as()->getJson('/api/v1/merchandise/fabric-booking-items/BFB-00001')->assertOk()->json('data');
        $this->assertSame([false, 1, 'KG'], [$panel['editable'], count($panel['lines']), $panel['lines'][0]['uom']]);
        $this->as()->postJson('/api/v1/merchandise/fabric-booking-items/BFB-00001', ['fabric_type_id' => $sj, 'qty' => 5])->assertUnprocessable()
            ->assertJsonValidationErrors(['booking' => "A bulk booking's fabric is keyed on Bulk Fabric Booking Entry."]);
        // The requisition endpoints never reach a bulk booking.
        $this->as()->getJson('/api/v1/merchandise/fabric-requisitions/BFB-00001')->assertNotFound();

        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($technician)->getJson('/api/v1/merchandise/fabric-booking-items')->assertForbidden();
        $this->as($manager)->getJson('/api/v1/merchandise/fabric-requisitions?type=ADDITIONAL')->assertOk();
        $this->as($manager)->postJson('/api/v1/merchandise/fabric-requisitions?type=ADDITIONAL', ['booking_date' => '2026-10-16'])->assertForbidden();
    }

    private string $buyerId;

    private string $sizeId;

    /** A style 637PK of LPP and an order of it: [style, office, order]. */
    private function order(): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Spolka Akcyjna Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $this->buyerId = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $this->sizeId = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $this->buyerId, 'buyer_office_id' => $office, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$this->sizeId]]]])
            ->assertCreated()->json('data.id');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 100, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data');

        return [$style, $office, $order];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
