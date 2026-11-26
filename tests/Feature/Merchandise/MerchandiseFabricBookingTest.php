<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\Size;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Merchandise Desk → Fabric Booking (AnnotechERP 06/11): Bulk Fabric Booking
 * Entry — a booking keyed against an order and saved whole, numbered BFB-
 * 00001 and revised on each later save; each line's pieces and finished
 * fabric quantity derived from the order's colour / size breakdown; Suggest
 * reading the style's CAD consumption. Approved Fabric Booking List — a bulk
 * booking sent through the Approval Center, locked while it waits and once
 * approved, and listed with every other approved booking.
 */
class MerchandiseFabricBookingTest extends TestCase
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

    public function test_a_bulk_booking_derives_each_lines_finished_fabric_and_is_revised_on_save(): void
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $style = $this->style($buyer, $office, '637PK');
        foreach (['S', 'L'] as $code) {
            $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => $code, 'name' => $code])->assertCreated();
        }
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 100, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data');
        $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/sizes", ['rows' => [
            ['colour' => 'Navy', 'size' => 'S', 'qty' => 120], ['colour' => 'Navy', 'size' => 'M', 'qty' => 240], ['colour' => 'White', 'size' => 'M', 'qty' => 120],
        ]])->assertOk();
        $navy = $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'SOLID', 'name' => 'Navy'])->assertCreated()->json('data.id');
        $sj = $this->as()->postJson('/api/v1/erp/fabric-types', ['code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'SJ', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'sort_order' => 1])->assertCreated()->json('data.id');
        $body = GarmentPart::query()->create(['code' => 'BD', 'name' => 'Body', 'active' => true, 'is_body' => true])->id;

        $options = $this->as()->getJson('/api/v1/merchandise/fabric-bookings/options')->assertOk()->json('data');
        $this->assertSame(['637PK — Hoodie', ['LETTER', 'NUMERIC', 'AGE'], ['KNIT', 'WOVEN', 'TRIMS']], [$options['styles'][0]['label'], $options['size_groups'], $options['kinds']]);

        $line = [
            'fabric_kind' => 'KNIT', 'fabric_type_id' => $sj, 'garment_part_id' => $body, 'finish_dia' => 72, 'dia_unit' => 'INCH', 'dia_type' => 'OPEN',
            'consumption' => 1.2, 'cons_unit' => 'KG', 'cons_per' => 'DZ', 'all_orders' => false, 'gmt_style_id' => $style,
            'all_colours' => false, 'gmt_colour_id' => $navy, 'all_sizes' => true, 'fab_same_as_gmt' => true, 'cutting_wastage' => 5, 'add_qty' => 2, 'gsm' => 160,
        ];
        $head = ['booking_date' => '2026-10-15', 'style_id' => $style, 'order_id' => $order['id'], 'currency' => 'USD', 'conv_rate' => 1, 'item_model' => ' 637PK · Hoodie '];

        $this->as()->postJson('/api/v1/merchandise/fabric-bookings?type=BULK', $head + ['lines' => []])->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'Add at least one line to the grid before saving.');
        $this->as()->postJson('/api/v1/merchandise/fabric-bookings?type=BULK', ['lines' => [$line]])->assertUnprocessable()
            ->assertJsonPath('errors.style_id.0', 'Job/Working Style# is required.')->assertJsonPath('errors.order_id.0', 'Order# is required.')->assertJsonPath('errors.item_model.0', 'Item/Model # is required.');
        $this->as()->postJson('/api/v1/merchandise/fabric-bookings?type=BULK', $head + ['lines' => [['gmt_colour_id' => null] + $line]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.gmt_colour_id' => 'Pick the GMT Color, or tick All?.']);

        $saved = $this->as()->postJson('/api/v1/merchandise/fabric-bookings?type=BULK', $head + ['lines' => [$line]])->assertCreated()->json('data');
        $this->assertSame(['BFB-00001', 0, 'BULK', 'DRAFT', '637PK · Hoodie', $office], [$saved['booking_no'], $saved['revision'], $saved['booking_type'], $saved['status'], $saved['item_model'], $saved['buyer_office_id']]);
        // Navy, every size: 360 pieces; 1.2 kg a dozen is 36 kg, +5 % is 37.8, +2 added is 39.8.
        $this->assertEquals([360, 39.8, 160], [$saved['lines'][0]['pieces'], $saved['lines'][0]['finish_qty'], $saved['lines'][0]['gsm']]);

        // Navy in the letter sizes counts only the sizes the size master names: S and M here (L is unbooked).
        $letter = $this->as()->putJson("/api/v1/merchandise/fabric-bookings/{$saved['booking_no']}", $head + ['lines' => [['all_sizes' => false, 'size_group' => 'LETTER', 'cons_per' => 'PCS', 'consumption' => 0.1, 'add_qty' => 0, 'cutting_wastage' => 0] + $line]])->assertOk()->json('data');
        $this->assertSame([1, 'BFB-00001'], [$letter['revision'], $letter['booking_no']]);
        $this->assertEquals([360, 36], [$letter['lines'][0]['pieces'], $letter['lines'][0]['finish_qty']]);
        // Every colour of every order of the style: 480 pieces.
        $all = $this->as()->putJson("/api/v1/merchandise/fabric-bookings/{$saved['booking_no']}", $head + ['lines' => [['all_orders' => true, 'all_colours' => true, 'gmt_colour_id' => null, 'add_qty' => 0, 'cutting_wastage' => 0] + $line]])->assertOk()->json('data');
        $this->assertSame(2, $all['revision']);
        $this->assertEquals([480, 48], [$all['lines'][0]['pieces'], $all['lines'][0]['finish_qty']]);

        $list = $this->as()->getJson('/api/v1/merchandise/fabric-bookings?type=BULK')->assertOk()->json('data');
        $this->assertSame([['BFB-00001', '637PK', $order['order_no'], 2, 1, 'LPP SPOLKA AKCYJNA OFFICE']], array_map(fn ($r) => [$r['booking_no'], $r['work_style'], $r['order_no'], $r['revision'], $r['items'], $r['buyer_ac']], $list));
        $this->assertEquals(48, $list[0]['qty']);
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/fabric-bookings?type=BUFFER')->json('data'));
        $this->assertSame('BFB-00002', $this->as()->postJson('/api/v1/merchandise/fabric-bookings?type=BULK', $head + ['lines' => [$line]])->assertCreated()->json('data.booking_no'));

        $this->as()->deleteJson('/api/v1/merchandise/fabric-bookings/BFB-00002')->assertOk();
        $this->as()->getJson('/api/v1/merchandise/fabric-bookings/BFB-00002')->assertNotFound();
    }

    public function test_a_bulk_booking_is_approved_through_the_approval_center_and_listed(): void
    {
        [$no, $head, $line, $order] = $this->draft();

        // A fabric booking chain: the factory manager signs.
        $workflow = ApprovalWorkflow::create(['company_id' => $this->delta->id, 'name' => 'Fabric booking', 'entity_type' => 'FABRIC_BOOKING', 'active' => true]);
        ApprovalRule::create([
            'company_id' => $this->delta->id, 'workflow_id' => $workflow->id, 'name' => 'Factory manager', 'sequence' => 1,
            'role_id' => Role::whereNull('company_id')->where('name', 'FACTORY_MANAGER')->firstOrFail()->id, 'condition_json' => ['min_cost' => '0'],
        ]);
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');

        $sent = $this->as()->postJson("/api/v1/merchandise/fabric-bookings/{$no}/submit")->assertOk()->json('data');
        $this->assertSame(['SUBMITTED', 'PENDING', true], [$sent['status'], $sent['approval_status'], $sent['locked']]);
        $request = ApprovalRequest::query()->where('entity_type', 'FABRIC_BOOKING')->firstOrFail();
        // The value the chain reads: 39.8 kg at 3.5 a kg.
        $this->assertSame(['139.3000', 'USD'], [$request->context_json['cost'], $request->context_json['currency']]);

        // While it waits it is neither changed, removed nor sent twice.
        $this->as()->putJson("/api/v1/merchandise/fabric-bookings/{$no}", $head + ['lines' => [$line]])->assertUnprocessable()
            ->assertJsonValidationErrors(['booking' => 'This booking is Submitted — it can no longer be changed or removed.']);
        $this->as()->deleteJson("/api/v1/merchandise/fabric-bookings/{$no}")->assertUnprocessable();
        $this->as()->postJson("/api/v1/merchandise/fabric-bookings/{$no}/submit")->assertUnprocessable()
            ->assertJsonValidationErrors(['booking' => 'Only a bulk booking in Draft or Rejected can be sent for approval.']);
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/fabric-bookings/approved')->json('data'));

        // Rejected, it is the merchandiser's to change and send again.
        $this->as($manager)->postJson("/api/v1/approval-requests/{$request->id}/reject", ['comment' => 'Wrong colour'])->assertOk();
        $this->assertSame('REJECTED', $this->as()->getJson("/api/v1/merchandise/fabric-bookings/{$no}")->json('data.status'));
        $this->as()->putJson("/api/v1/merchandise/fabric-bookings/{$no}", $head + ['lines' => [$line]])->assertOk();
        $this->as()->postJson("/api/v1/merchandise/fabric-bookings/{$no}/submit")->assertOk();

        $again = ApprovalRequest::query()->where('entity_type', 'FABRIC_BOOKING')->where('status', 'PENDING')->firstOrFail();
        $this->as($manager)->postJson("/api/v1/approval-requests/{$again->id}/approve", ['comment' => 'Agreed'])->assertOk();
        $approved = $this->as()->getJson("/api/v1/merchandise/fabric-bookings/{$no}")->assertOk()->json('data');
        $this->assertSame(['APPROVED', 'APPROVED', $manager->name, true], [$approved['status'], $approved['approval_status'], $approved['approved_by'], $approved['locked']]);
        $this->assertNotNull($approved['approved_at']);

        $list = $this->as()->getJson('/api/v1/merchandise/fabric-bookings/approved')->assertOk()->json('data');
        $this->assertSame([['BULK', 'BFB-00001', 1, '637PK', '529', $order['order_no'], 'LPP SPOLKA AKCYJNA OFFICE', false]], array_map(
            fn ($r) => [$r['booking_type'], $r['booking_no'], $r['revision'], $r['style'], $r['job_no'], $r['order_no'], $r['buyer_ac'], $r['send_mail']], $list,
        ));
        $this->assertSame(now()->toDateString(), $list[0]['approved_at']);

        // A manager reads the list but sends nothing.
        $this->as($manager)->getJson('/api/v1/merchandise/fabric-bookings/approved')->assertOk();
        $this->as($manager)->postJson("/api/v1/merchandise/fabric-bookings/{$no}/submit")->assertForbidden();
    }

    public function test_with_no_fabric_booking_chain_a_submitted_booking_is_approved_at_once(): void
    {
        [$no] = $this->draft();

        $sent = $this->as()->postJson("/api/v1/merchandise/fabric-bookings/{$no}/submit")->assertOk()->json('data');
        $this->assertSame(['APPROVED', 'NOT_REQUIRED', null, true], [$sent['status'], $sent['approval_status'], $sent['approved_by'], $sent['locked']]);
        $this->assertSame(['BFB-00001'], array_column($this->as()->getJson('/api/v1/merchandise/fabric-bookings/approved')->json('data'), 'booking_no'));
        $this->as()->deleteJson("/api/v1/merchandise/fabric-bookings/{$no}")->assertUnprocessable()
            ->assertJsonValidationErrors(['booking' => 'This booking is Approved — it can no longer be changed or removed.']);
    }

    public function test_suggest_reads_the_styles_cad_consumption(): void
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $style = $this->style($buyer, $office, '637PK');
        $this->assertSame(['record' => null, 'parts' => []], $this->as()->getJson("/api/v1/merchandise/fabric-bookings/suggest?style_id={$style}")->assertOk()->json('data'));

        $body = GarmentPart::query()->create(['code' => 'BD', 'name' => 'Body', 'active' => true, 'is_body' => true])->id;
        $record = $this->as()->postJson('/api/v1/erp/merch-cad-records', ['category' => 'BULK', 'job_no' => '529', 'record_date' => '2026-10-10', 'style_id' => $style])->assertCreated()->json('data.id');
        $this->as()->putJson("/api/v1/merchandise/cad-records/{$record}/sizes", ['rows' => [
            ['size' => 'M', 'garment_part_id' => $body, 'consumption' => 1.1, 'uom' => 'KG'], ['size' => 'L', 'garment_part_id' => $body, 'consumption' => 1.3, 'uom' => 'KG'],
        ]])->assertOk();

        $suggested = $this->as()->getJson("/api/v1/merchandise/fabric-bookings/suggest?style_id={$style}")->assertOk()->json('data');
        $this->assertSame(['529', 'Body', true, 'KG'], [$suggested['record'], $suggested['parts'][0]['part'], $suggested['parts'][0]['is_body'], $suggested['parts'][0]['uom']]);
        $this->assertEquals(1.2, $suggested['parts'][0]['consumption']);
    }

    public function test_bookings_are_closed_to_a_technician_and_read_only_to_a_manager(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');

        $this->as($technician)->getJson('/api/v1/merchandise/fabric-bookings?type=BULK')->assertForbidden();
        $this->as($manager)->getJson('/api/v1/merchandise/fabric-bookings?type=BULK')->assertOk();
        $this->as($manager)->postJson('/api/v1/merchandise/fabric-bookings?type=BULK', [])->assertForbidden();
    }

    /** A saved draft bulk booking: [booking no, head, line, order]. */
    private function draft(): array
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $style = $this->style($buyer, $office, '637PK');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 100, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data');
        $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/sizes", ['rows' => [['colour' => 'Navy', 'size' => 'M', 'qty' => 360]]])->assertOk();
        $navy = $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'SOLID', 'name' => 'Navy'])->assertCreated()->json('data.id');
        $sj = $this->as()->postJson('/api/v1/erp/fabric-types', ['code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'SJ', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'sort_order' => 1])->assertCreated()->json('data.id');
        $line = [
            'fabric_kind' => 'KNIT', 'fabric_type_id' => $sj, 'finish_dia' => 72, 'dia_unit' => 'INCH', 'dia_type' => 'OPEN', 'consumption' => 1.2, 'cons_unit' => 'KG', 'cons_per' => 'DZ',
            'all_orders' => false, 'gmt_style_id' => $style, 'all_colours' => false, 'gmt_colour_id' => $navy, 'all_sizes' => true, 'fab_same_as_gmt' => true, 'cutting_wastage' => 5, 'add_qty' => 2, 'rate' => 3.5,
        ];
        $head = ['booking_date' => '2026-10-15', 'style_id' => $style, 'order_id' => $order['id'], 'currency' => 'USD', 'conv_rate' => 1, 'item_model' => '637PK · Hoodie'];
        $no = $this->as()->postJson('/api/v1/merchandise/fabric-bookings?type=BULK', $head + ['lines' => [$line]])->assertCreated()->json('data.booking_no');

        return [$no, $head, $line, $order];
    }

    private function buyer(string $code, string $name): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => "{$name} Office", 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => $code, 'name' => $name, 'short_name' => $code, 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');

        return [$buyer, $office];
    }

    private function style(string $buyer, string $office, string $ref): string
    {
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id')
            ?? Size::query()->where('code', 'M')->value('id');

        return $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => $ref, 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
