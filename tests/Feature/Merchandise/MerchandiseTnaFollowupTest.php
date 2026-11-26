<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\FabricBooking;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\TnaTask;
use App\Modules\Merchandise\Models\TnaTaskGroup;
use App\Modules\Merchandise\Models\TnaTemplate;
use App\Modules\Merchandise\Models\TnaTemplateTask;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Merchandise Desk → T&A Followup (AnnotechERP 06/12): an order's calendar
 * raised from the TNA Master and counted back from the shipment date; the
 * milestones a module records read from it, the others recorded here;
 * recalculation re-dating only what is still open; the change history and
 * the order's quantity by colour and size.
 */
class MerchandiseTnaFollowupTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-10-16 10:00:00');
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_a_calendar_is_raised_from_the_task_master_followed_and_recalculated(): void
    {
        $order = $this->order();
        $tasks = $this->master();

        $options = $this->as()->getJson('/api/v1/merchandise/tna-followup/orders')->assertOk()->json('data');
        $this->assertSame([[$order->order_no, "{$order->order_no} · LPP · 637PK · PO 1531"]], array_map(fn ($o) => [$o['value'], $o['label']], $options));

        $empty = $this->as()->getJson("/api/v1/merchandise/tna-followup/{$order->order_no}")->assertOk()->json('data');
        $this->assertSame([[], 0, 'LPP', '637PK', '1531', '2026-11-01'], [$empty['rows'], $empty['kpis']['total'], $empty['order']['buyer'], $empty['order']['style'], $empty['order']['po_no'], $empty['order']['ship_date']]);
        // The quantity by colour, size and the two together.
        $this->assertEquals([['colour' => 'Navy', 'qty' => 360], ['colour' => 'White', 'qty' => 120]], $empty['colours']);
        $this->assertEquals(['sizes' => ['S', 'M'], 'rows' => [['colour' => 'Navy', 'cells' => ['S' => 120, 'M' => 240], 'total' => 360], ['colour' => 'White', 'cells' => ['M' => 120], 'total' => 120]]], $empty['matrix']);

        // No template fits, so each active task's own day from shipment (negative is before): earliest first.
        $data = $this->as()->postJson("/api/v1/merchandise/tna-followup/{$order->order_no}/recalculate")->assertOk()->json('data');
        $this->assertSame([
            ['Rcv Order Confirm', '2026-08-03', '2026-10-08', 'COMPLETED', 'General Process'],
            ['Issue PI/TT/Sign Sale Contract', '2026-08-13', null, 'OVERDUE', 'General Process'],
            ['Bulk Fabric Booking', '2026-09-02', null, 'OVERDUE', 'Bulk Production'],
            ['Sewing Complete', '2026-10-22', null, 'PENDING', 'Bulk Production'],
            ['Ex-Factory', '2026-11-01', null, 'PLANNED', 'Shipment'],
        ], array_map(fn ($r) => [$r['task'], $r['plan_date'], $r['actual_date'], $r['status'], $r['department']], $data['rows']));
        $this->assertSame([-64, 6, 16], [$data['rows'][1]['d2g'], $data['rows'][3]['d2g'], $data['rows'][4]['d2g']]);
        $this->assertSame(['Order confirmed on 08-Oct-2026', true], [$data['rows'][0]['source_text'], $data['rows'][0]['auto']]);
        $this->assertSame(['total' => 5, 'completed' => 1, 'in_progress' => 0, 'pending' => 1, 'overdue' => 2, 'due_today' => 0, 'progress' => 20], $data['kpis']);
        $this->assertSame([['Rcv Order Confirm', true], ['Bulk Fabric Booking', false], ['Sewing Complete', false]], array_map(fn ($c) => [$c['task'], $c['done']], $data['modules']));

        $issue = $data['rows'][1]['id'];
        $booking = $data['rows'][2]['id'];
        $this->as()->putJson("/api/v1/merchandise/tna-followup/{$order->order_no}/milestones/{$booking}", ['actual_date' => '2026-10-10'])->assertUnprocessable()
            ->assertJsonValidationErrors(['actual_date' => 'The Fabric Booking records this task — its date cannot be entered here.']);
        $this->as()->putJson("/api/v1/merchandise/tna-followup/{$order->order_no}/milestones/{$issue}", ['actual_date' => '2026-10-20'])->assertUnprocessable()
            ->assertJsonValidationErrors(['actual_date' => 'The actual date cannot be after today.']);
        $saved = $this->as()->putJson("/api/v1/merchandise/tna-followup/{$order->order_no}/milestones/{$issue}", ['actual_date' => '2026-10-10', 'remarks' => 'Signed after buyer revision'])->assertOk()->json('data');
        $this->assertSame(['2026-10-10', 'COMPLETED', 100], [$saved['rows'][1]['actual_date'], $saved['rows'][1]['status'], $saved['rows'][1]['completion']]);
        $this->assertSame([['task' => 'Issue PI/TT/Sign Sale Contract', 'remarks' => 'Signed after buyer revision']], $saved['remarks']);
        $exFactory = $saved['rows'][4]['id'];
        $this->assertSame('2026-10-16', $this->as()->putJson("/api/v1/merchandise/tna-followup/{$order->order_no}/milestones/{$exFactory}", ['complete_today' => true])->assertOk()->json('data.rows.4.actual_date'));
        $this->as()->putJson("/api/v1/merchandise/tna-followup/{$order->order_no}/milestones/{$exFactory}", ['actual_date' => null])->assertOk();

        // The bulk fabric booking approved: read from the module, not entered.
        FabricBooking::query()->create(['booking_type' => 'BULK', 'booking_no' => 'BFB-00001', 'booking_date' => '2026-10-12', 'order_id' => $order->id, 'style_id' => $order->style_id, 'currency' => 'USD', 'conv_rate' => 1, 'status' => 'APPROVED', 'approved_at' => '2026-10-14 09:00:00']);
        $read = $this->as()->getJson("/api/v1/merchandise/tna-followup/{$order->order_no}")->assertOk()->json('data');
        $this->assertSame(['2026-10-14', 'COMPLETED', 'Bulk fabric booking BFB-00001 approved on 14-Oct-2026'], [$read['rows'][2]['actual_date'], $read['rows'][2]['status'], $read['rows'][2]['source_text']]);
        $this->assertSame([['PURCHASE_ORDER', '1531'], ['BOOKING_BULK', 'BFB-00001']], array_map(fn ($d) => [$d['document'], $d['reference']], $read['documents']));

        // A later shipment re-dates only the open milestones.
        $order->forceFill(['ship_date' => '2026-11-11'])->save();
        $again = $this->as()->postJson("/api/v1/merchandise/tna-followup/{$order->order_no}/recalculate")->assertOk()->json('data');
        $this->assertSame(['2026-08-03', '2026-08-13', '2026-09-02', '2026-11-01', '2026-11-11'], array_column($again['rows'], 'plan_date'));
        $history = array_column($again['history'], 'message');
        $this->assertSame("{$order->order_no}: 2 plan dates recalculated from shipment 11-Nov-2026, 0 milestones added", $history[0]);
        $this->assertContains('Bulk Fabric Booking updated from Fabric Booking (14-Oct-2026)', $history);
        $this->assertContains('Issue PI/TT/Sign Sale Contract completed on 10-Oct-2026', $history);
        $this->assertContains('Remarks saved on Issue PI/TT/Sign Sale Contract', $history);
        $this->assertContains('Ex-Factory reopened', $history);
        $this->assertNotEmpty($tasks);
    }

    public function test_the_orders_template_sets_its_calendar(): void
    {
        $order = $this->order();
        $tasks = $this->master();
        $template = TnaTemplate::query()->create(['code' => 'TN30', 'scope' => 'GENERAL', 'order_type' => 'NEW', 'gmt_type' => 'BASIC', 'lead_from' => 20, 'lead_to' => 30, 'std_days' => 24, 'before_swing' => 0, 'after_swing' => 0]);
        TnaTemplateTask::query()->create(['tna_template_id' => $template->id, 'tna_task_id' => $tasks['Bulk Fabric Booking'], 'offset_days' => -20, 'sort_order' => 1]);
        TnaTemplateTask::query()->create(['tna_template_id' => $template->id, 'tna_task_id' => $tasks['Ex-Factory'], 'offset_days' => 0, 'sort_order' => 2]);

        // 8 Oct → 1 Nov is 24 days: the template for new orders of 20–30 days fits.
        $rows = $this->as()->postJson("/api/v1/merchandise/tna-followup/{$order->order_no}/recalculate")->assertOk()->json('data.rows');
        $this->assertSame([['Bulk Fabric Booking', '2026-10-12'], ['Ex-Factory', '2026-11-01']], array_map(fn ($r) => [$r['task'], $r['plan_date']], $rows));

        $order->forceFill(['ship_date' => null])->save();
        $this->as()->postJson("/api/v1/merchandise/tna-followup/{$order->order_no}/recalculate")->assertUnprocessable()
            ->assertJsonValidationErrors(['order' => 'This order has no shipment date, so its calendar cannot be counted back.']);

        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($technician)->getJson('/api/v1/merchandise/tna-followup/orders')->assertForbidden();
        $this->as($manager)->getJson("/api/v1/merchandise/tna-followup/{$order->order_no}")->assertOk();
        $this->as($manager)->postJson("/api/v1/merchandise/tna-followup/{$order->order_no}/recalculate")->assertForbidden();
    }

    /** The TNA Master: task groups and tasks, one of them switched off. @return array<string, string> name → id */
    private function master(): array
    {
        $groups = collect(['General Process', 'Bulk Production', 'Shipment'])->mapWithKeys(fn ($g, $i) => [$g => TnaTaskGroup::query()->create(['code' => "G{$i}", 'name' => $g, 'active' => true])->id]);
        $tasks = [];
        foreach ([
            ['Rcv Order Confirm', 'General Process', 'Order Module', -90],
            // Listed early in the master, dated last in the calendar.
            ['Ex-Factory', 'Shipment', null, 0],
            ['Issue PI/TT/Sign Sale Contract', 'General Process', null, -80],
            ['Bulk Fabric Booking', 'Bulk Production', 'Fabric Booking', -60],
            ['Sewing Complete', 'Bulk Production', 'Sewing Module', -10],
            ['Final Inspection', 'Shipment', null, -2, false],
        ] as $i => $t) {
            $tasks[$t[0]] = TnaTask::query()->create(['tna_task_group_id' => $groups[$t[1]], 'code' => 'T'.($i + 1), 'sort_order' => ($i + 1) * 10, 'name' => $t[0], 'source_module' => $t[2], 'offset_days' => $t[3], 'active' => $t[4] ?? true])->id;
        }

        return $tasks;
    }

    /** A confirmed new order of 637PK (LPP), 8 Oct → 1 Nov, with its colour / size breakdown. */
    private function order(): Order
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $id = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 480, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');
        $this->as()->putJson("/api/v1/merchandise/orders/{$id}/sizes", ['rows' => [['colour' => 'Navy', 'size' => 'S', 'qty' => 120], ['colour' => 'Navy', 'size' => 'M', 'qty' => 240], ['colour' => 'White', 'size' => 'M', 'qty' => 120]]])->assertOk();
        $order = Order::query()->findOrFail($id);
        $order->forceFill(['status' => 'CONFIRMED', 'order_type' => 'BULK_NEW', 'conf_date' => '2026-10-08', 'ship_date' => '2026-11-01', 'po_no' => '1531', 'buyer_id' => $buyer])->save();

        return $order->refresh();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
