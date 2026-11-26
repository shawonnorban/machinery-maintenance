<?php

declare(strict_types=1);

namespace Tests\Feature\Cutting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Cutting Floor → Reports (AnnotechERP 19/04): every bundle, and the bundles
 * counted per PO, style and size, read with Brickland's filter — and who may
 * read them.
 */
class CuttingReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    /** @var array<string, string> */
    private array $orders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $lpp = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $roly = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'ROLY', 'name' => 'Roly SL', 'short_name' => 'ROLY', 'pi_address' => 'Valencia', 'registered_from' => '2026-01-01', 'active' => true])->id;
        foreach ([['11565705', '868MI', $lpp], ['4500085753', 'CA-6627', $roly]] as [$po, $style, $buyer]) {
            $id = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => "ORD-{$po}", 'job_no' => $style, 'style_no' => $style, 'buyer_id' => $buyer, 'order_type' => 'BULK_NEW', 'total_qty' => 500,
                'ship_date' => '2026-12-15', 'status' => 'CONFIRMED', 'po_no' => $po])->id;
            foreach (['XL', 'S', 'M'] as $i => $size) {
                DB::table('merch_order_sizes')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'order_id' => $id, 'colour' => 'Black', 'size' => $size, 'qty' => 100, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->orders[$po] = $id;
        }
    }

    public function test_the_bundle_reports_count_the_cards(): void
    {
        $this->card('11565705', 'S', 1, 36);
        $this->card('11565705', 'M', 1, 40);
        $this->card('11565705', 'XL', 1, 30);
        $this->card('4500085753', 'S', 1, 25);
        $cancel = $this->card('4500085753', 'M', 1, 10);
        $this->as()->postJson("/api/v1/cutting/bundles/{$cancel}/cancel")->assertOk();

        $all = $this->as()->getJson('/api/v1/cutting/reports/bundle')->assertOk()->json('data');
        $this->assertSame([5, 141, 'ALL'], [$all['total_bundles'], $all['total_qty'], $all['status']]);
        $this->as()->getJson('/api/v1/cutting/reports/bundle?status=CANCELLED')->assertJsonPath('data.total_bundles', 1)->assertJsonPath('data.rows.0.bundle_no', $cancel);
        $this->as()->getJson('/api/v1/cutting/reports/bundle?buyer=rol')->assertJsonPath('data.total_bundles', 2)->assertJsonPath('data.rows.0.buyer', 'ROLY');
        $this->as()->getJson('/api/v1/cutting/reports/bundle?serial=33')->assertJsonPath('data.total_bundles', 2);
        $this->as()->getJson('/api/v1/cutting/reports/bundle?size=s&po=1156')->assertJsonPath('data.total_qty', 36);

        // The grouped reports leave the cancelled card out.
        $po = $this->as()->getJson('/api/v1/cutting/reports/po')->json('data');
        $this->assertSame([['11565705', '868MI', 3, 106], ['4500085753', 'CA-6627', 1, 25]], array_map(fn ($r) => [$r['po_no'], $r['style'], $r['bundles'], $r['qty']], $po['rows']));
        $this->assertSame([4, 131], [$po['total_bundles'], $po['total_qty']]);
        $this->as()->getJson('/api/v1/cutting/reports/style')->assertJsonCount(2, 'data.rows')->assertJsonPath('data.rows.1.style', 'CA-6627');
        $size = $this->as()->getJson('/api/v1/cutting/reports/size')->json('data.rows');
        $this->assertSame([['S', 2, 61], ['M', 1, 40], ['XL', 1, 30]], array_map(fn ($r) => [$r['size'], $r['bundles'], $r['qty']], $size));
        $this->as()->getJson('/api/v1/cutting/reports/size?status=CANCELLED')->assertJsonPath('data.rows.0.size', 'M')->assertJsonPath('data.total_qty', 10);
        $this->as()->getJson('/api/v1/cutting/reports/size?from=2026-12-01&to=2026-11-01')->assertUnprocessable()->assertJsonPath('errors.to.0', 'To Date cannot be before From Date.');
        $this->as()->getJson('/api/v1/cutting/reports/colour')->assertNotFound();
    }

    public function test_the_additional_cutting_list_shows_the_cards_cut_past_the_order(): void
    {
        // 11565705 orders 300 Black: 200 + 90 regular, then XL 1–30 crosses the order (20 over), XL 31–50 is all over.
        $this->card('11565705', 'S', 1, 200);
        $this->card('11565705', 'M', 1, 90);
        $partial = $this->card('11565705', 'XL', 1, 30, cuttingNo: 2);
        $over = $this->card('11565705', 'XL', 31, 50, cuttingNo: 2);
        // 4500085753 orders 300: one card of 310 is partly additional; a cancelled additional card never counts.
        $this->card('4500085753', 'S', 1, 310, batch: 'B-7');
        $cancel = $this->card('4500085753', 'M', 1, 5);
        $this->as()->postJson("/api/v1/cutting/bundles/{$cancel}/cancel")->assertOk();
        // A PO without a quantity: every piece is additional and the percentage is N/A.
        $zero = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'ORD-SMS', 'job_no' => 'SMS', 'style_no' => 'SMS-1', 'buyer_id' => Buyer::query()->value('id'), 'order_type' => 'BULK_NEW', 'total_qty' => 0,
            'ship_date' => '2026-12-15', 'status' => 'CONFIRMED', 'po_no' => 'SMS-01'])->id;
        $this->orders['SMS-01'] = $zero;
        $this->card('SMS-01', 'M', 1, 15);

        $all = $this->as()->getJson('/api/v1/cutting/additional-cutting')->assertOk()->json('data');
        $this->assertSame(['ANY_ADDITIONAL', 4, 20 + 20 + 10 + 15], [$all['status'], $all['total_bundles'], $all['total_additional']]);
        $this->assertSame(
            [['11565705', 300, 340, 40, 13.33], ['4500085753', 300, 310, 10, 3.33], ['SMS-01', 0, 15, 15, null]],
            array_map(fn ($g) => [$g['po_no'], $g['order_qty'], $g['cut'], $g['additional'], $g['additional_pct']], $all['groups']),
        );
        $byNo = collect($all['bundles'])->keyBy('bundle_no');
        $p = $byNo[$partial];
        $this->assertSame(['PARTIAL_ADDITIONAL', 30, 20, 300, 340, 40], [$p['cutting_status'], $p['qty'], $p['additional_qty'], $p['order_qty'], $p['total_cut'], $p['total_additional']]);
        $this->assertSame(['ADDITIONAL', 20], [$byNo[$over]['cutting_status'], $byNo[$over]['additional_qty']]);
        $this->assertArrayNotHasKey($cancel, $byNo->all());

        $this->as()->getJson('/api/v1/cutting/additional-cutting?status=PARTIAL_ADDITIONAL')->assertJsonPath('data.total_bundles', 2)->assertJsonPath('data.total_additional', 30);
        $this->as()->getJson('/api/v1/cutting/additional-cutting?status=ADDITIONAL')->assertJsonPath('data.total_bundles', 2);
        $this->as()->getJson("/api/v1/cutting/additional-cutting?bundle_no={$over}")->assertJsonPath('data.total_bundles', 1)->assertJsonCount(1, 'data.groups');
        $this->as()->getJson('/api/v1/cutting/additional-cutting?cutting_no=2&size=xl')->assertJsonPath('data.total_bundles', 2);
        $this->as()->getJson('/api/v1/cutting/additional-cutting?batch_no=b-7&colour=bla')->assertJsonPath('data.bundles.0.po_no', '4500085753');
        $this->as()->getJson('/api/v1/cutting/additional-cutting?from=2026-01-01&to=2026-01-31')->assertJsonPath('data.total_bundles', 0)->assertJsonPath('data.groups', []);
        $this->as()->getJson('/api/v1/cutting/additional-cutting?status=REGULAR')->assertUnprocessable();

        // The Cutting List counts the same PO the same way.
        $list = collect($this->as()->getJson('/api/v1/cutting/cutting-list')->assertOk()->json('data'))->keyBy('po_no');
        $this->assertSame([300, 340, 40, 13.33], [$list['11565705']['order_qty'], $list['11565705']['already_cut'], $list['11565705']['additional'], $list['11565705']['additional_pct']]);
    }

    public function test_who_may_read_the_bundle_reports(): void
    {
        $store = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($store)->getJson('/api/v1/cutting/reports/size')->assertOk();
        $this->withToken($store)->getJson('/api/v1/cutting/additional-cutting')->assertOk();
        $tech = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($tech)->getJson('/api/v1/cutting/reports/bundle')->assertForbidden();
        $this->withToken($tech)->getJson('/api/v1/cutting/additional-cutting')->assertForbidden();
    }

    private function card(string $po, string $size, int $from, int $to, int $cuttingNo = 1, string $batch = 'B-1'): string
    {
        return $this->as()->postJson('/api/v1/cutting/bundles', ['order_id' => $this->orders[$po], 'colour' => 'Black', 'cutting_no' => $cuttingNo, 'size' => $size, 'serial_from' => $from, 'serial_to' => $to, 'parts_name' => 'Back Panel', 'batch_no' => $batch])
            ->assertCreated()->json('data.bundle_no');
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
