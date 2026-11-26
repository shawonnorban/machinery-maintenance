<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Production (AnnotechERP 15/04): a Solid sample knit plan
 * — lines required, kept as a draft, refused at Submit Requisition until it
 * names its store and type and every line its registered yarn, then turned
 * into the Yarn Store's own requisition, its status following what the store
 * does with it — and a direct fabric booking: buyer and order required and
 * agreeing, each line a fabric type or the fabric in words, Draft →
 * Submitted → Approved, and Cancelled.
 */
class KnittingSampleBookingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $buyer;

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
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000060', 'job_no' => '7701', 'style_no' => '770MR', 'buyer_id' => $this->buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
    }

    public function test_sample_plan_drafted_submitted_as_a_store_requisition(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $cotton = FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton', 'active' => true])->id;
        $count = KnitYarnCount::query()->create(['count_no' => 'YC-30-1', 'count_system' => 'Ne', 'description' => '30/1', 'count_value' => 30, 'is_single' => true, 'display_order' => 1, 'active' => true])->id;
        $options = $this->as()->getJson('/api/v1/knitting/prod/sample-plans/options')->assertOk()->json('data');
        $this->assertSame('770MR · LPP-2026-000060', collect($options['orders'])->firstWhere('value', $this->order)['label']);

        $line = ['order_id' => $this->order, 'style_text' => null, 'colour_name' => 'Navy', 'yarn_details' => '30/1 Combed Cotton', 'qty' => 8, 'uom' => 'kg', 'gsm' => 180,
            'finish_dia' => '72', 'machine_dia' => '30', 'gauge' => '24'];
        $p = ['plan_date' => $today, 'lines' => [$line]];
        $this->as()->postJson('/api/v1/knitting/prod/sample-plans/SOLID', ['lines' => []] + $p)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A knit plan with no lines on it asks the store for nothing.');
        $made = $this->as()->postJson('/api/v1/knitting/prod/sample-plans/SOLID', $p)->assertCreated()->json('data');
        $no = "SYR-S-{$ym}0001";
        $this->assertEquals([$no, 'DRAFT', 'LPP', '770MR · LPP-2026-000060', 8.0], [$made['plan_no'], $made['status'], $made['lines'][0]['buyer'], $made['lines'][0]['order'], $made['qty']]);
        $this->assertSame([], $this->as()->getJson('/api/v1/knitting/prod/sample-plans/YD')->json('data'));

        // Submit Requisition wants the store, the type and every line's registered yarn.
        $this->as()->postJson("/api/v1/knitting/prod/sample-plans/SOLID/{$no}/submit")->assertUnprocessable()
            ->assertJsonValidationErrors(['store_id', 'req_type', 'lines.0.composition_id' => 'Line 1 has no registered yarn — the store cannot issue against Yarn Details alone.']);
        $ready = ['store_id' => $this->store, 'req_type' => 'KNIT_SAMPLE', 'lines' => [['composition_id' => $cotton, 'yarn_count_id' => $count, 'colour_group' => 'Natural', 'brand' => 'Square'] + $line]] + $p;
        $this->as()->putJson("/api/v1/knitting/prod/sample-plans/SOLID/{$no}", $ready)->assertOk();
        $row = $this->as()->postJson("/api/v1/knitting/prod/sample-plans/SOLID/{$no}/submit")->assertOk()->json('data');
        $this->assertEquals(['REQ_SUBMITTED', "YIR-{$ym}0001"], [$row['status'], $row['req_no']]);
        $this->as()->putJson("/api/v1/knitting/prod/sample-plans/SOLID/{$no}", $ready)->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$no} is Requisition Submitted — only a draft can be rewritten.");

        // The store's requisition carries the plan: type, store, the registered yarn, Req Qty.
        $req = $this->as()->getJson("/api/v1/knitting/ystore/issue-reqs/YIR-{$ym}0001")->assertOk()->json('data');
        $this->assertEquals(['KNIT_SAMPLE', $this->store, $no, $cotton, $count, 'Natural', 8.0, 'OPEN'],
            [$req['req_type'], $req['store_id'], $req['program_no'], $req['lines'][0]['composition_id'], $req['lines'][0]['yarn_count_id'], $req['lines'][0]['colour'], $req['lines'][0]['req_qty'], $req['status']]);

        // What the store does with it is what the plan shows.
        DB::table('knit_yarn_issue_reqs')->where('req_no', "YIR-{$ym}0001")->update(['status' => 'PARTLY_ISSUED']);
        $this->assertSame('ISSUED', $this->as()->getJson('/api/v1/knitting/prod/sample-plans/SOLID')->json('data.0.status'));
        $this->assertCount(1, $this->as()->getJson('/api/v1/knitting/prod/sample-plans/SOLID?status=ISSUED')->json('data'));
        $this->as()->postJson("/api/v1/knitting/prod/sample-plans/SOLID/{$no}/cancel")->assertUnprocessable()->assertJsonValidationErrors('status');
        DB::table('knit_yarn_issue_reqs')->where('req_no', "YIR-{$ym}0001")->update(['status' => 'CLOSED']);
        $this->assertSame('CLOSED', $this->as()->getJson("/api/v1/knitting/prod/sample-plans/SOLID/{$no}")->json('data.status'));

        // A Y/D plan runs its own numbers; a draft can be cancelled.
        $yd = $this->as()->postJson('/api/v1/knitting/prod/sample-plans/YD', ['lines' => [['order_id' => null, 'style_text' => 'SS27 DEV-4'] + $line]] + $p)->assertCreated()->json('data.plan_no');
        $this->assertSame("SYR-Y-{$ym}0001", $yd);
        $this->as()->postJson("/api/v1/knitting/prod/sample-plans/YD/{$yd}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
    }

    public function test_direct_booking_submitted_approved_and_cancelled(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $line = ['fabric' => 'S/J 30/1 Combed 160 GSM', 'colour_name' => 'Navy', 'gsm' => 160, 'finish_dia' => 72, 'dia_unit' => 'INCH', 'width_type' => 'OPEN', 'qty' => 25, 'uom' => 'kg',
            'required_on' => now()->addDays(10)->toDateString()];
        $b = ['category_code' => 'FP01', 'buyer_id' => $this->buyer, 'order_id' => $this->order, 'prog_date' => $today, 'lines' => [$line]];
        $this->as()->postJson('/api/v1/knitting/prod/direct-bookings', ['lines' => []] + $b)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A booking with no fabric on it books nothing.');
        $this->as()->postJson('/api/v1/knitting/prod/direct-bookings', ['buyer_id' => null] + $b)->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', 'Buyer A/C is required.');
        $this->as()->postJson('/api/v1/knitting/prod/direct-bookings', ['lines' => [['fabric' => ''] + $line]] + $b)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.fabric' => 'Line 1 needs a Fabric Type or the Fabric in words.']);
        $other = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'HM', 'name' => 'H&M', 'short_name' => 'H&M', 'pi_address' => 'Stockholm', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->as()->postJson('/api/v1/knitting/prod/direct-bookings', ['buyer_id' => $other] + $b)->assertUnprocessable()->assertJsonPath('errors.order_id.0', 'That order belongs to another buyer.');

        $made = $this->as()->postJson('/api/v1/knitting/prod/direct-bookings', $b)->assertCreated()->json('data');
        $no = "DFB-{$ym}0001";
        $this->assertEquals([$no, 'DRAFT', 'Sample Booking', 'LPP', '770MR / LPP-2026-000060', 1, 25.0, '770MR'],
            [$made['booking_no'], $made['status'], $made['category'], $made['buyer'], $made['order'], count($made['lines']), $made['qty'], $made['job']]);

        $this->as()->postJson("/api/v1/knitting/prod/direct-bookings/{$no}/approve")->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->postJson("/api/v1/knitting/prod/direct-bookings/{$no}/submit")->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->as()->putJson("/api/v1/knitting/prod/direct-bookings/{$no}", $b)->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$no} is Submitted — only a draft can be rewritten.");
        $this->as()->postJson("/api/v1/knitting/prod/direct-bookings/{$no}/approve")->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->assertNotNull(DB::table('knit_direct_bookings')->where('booking_no', $no)->value('decided_by'));
        $this->as()->postJson("/api/v1/knitting/prod/direct-bookings/{$no}/cancel")->assertUnprocessable()->assertJsonValidationErrors('status');

        $two = $this->as()->postJson('/api/v1/knitting/prod/direct-bookings', ['category_code' => 'FP03'] + $b)->assertCreated()->json('data.booking_no');
        $this->as()->postJson("/api/v1/knitting/prod/direct-bookings/{$two}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertSame([$no], array_column($this->as()->getJson('/api/v1/knitting/prod/direct-bookings?status=APPROVED')->json('data'), 'booking_no'));
        $this->assertSame([$two], array_column($this->as()->getJson('/api/v1/knitting/prod/direct-bookings?category=FP03')->json('data'), 'booking_no'));
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
