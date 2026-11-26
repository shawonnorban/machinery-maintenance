<?php

declare(strict_types=1);

namespace Tests\Feature\Cutting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Cutting Floor (AnnotechERP 19/01–19/03): bundle cards cut against a PO's
 * style and colour — numbered, counted from their serial run, never
 * overlapping; what is cut beyond the colour's quantity marked additional;
 * printed, reprinted and cancelled; the cutting list and the dashboard
 * counted from the cards — and who may do what.
 */
class CuttingBundleTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $order;

    private string $day;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000008', 'job_no' => '868MI', 'style_no' => '868MI', 'buyer_id' => $buyer, 'order_type' => 'BULK_NEW', 'total_qty' => 160,
            'ship_date' => '2026-12-15', 'status' => 'CONFIRMED', 'po_no' => '11565705', 'item' => 'Basic T-Shirt'])->id;
        foreach ([['Navy', 'S', 40], ['Navy', 'M', 60], ['Red', 'M', 60]] as $i => [$colour, $size, $qty]) {
            DB::table('merch_order_sizes')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'order_id' => $this->order, 'colour' => $colour, 'size' => $size, 'qty' => $qty, 'sort_order' => $i + 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->day = CarbonImmutable::now('Asia/Dhaka')->format('Ymd');
    }

    public function test_a_card_is_numbered_counted_from_its_serials_and_never_overlaps(): void
    {
        $opts = $this->as()->getJson('/api/v1/cutting/options')->assertOk()->json('data');
        $o = collect($opts['orders'])->firstWhere('po_no', '11565705');
        $this->assertSame(['868MI', 'LPP', 'Basic T-Shirt', 100], [$o['style'], $o['buyer'], $o['garment'], $o['colours'][0]['qty']]);
        $this->assertSame("BND-{$this->day}-0001", $opts['next_bundle_no']);

        $b = $this->card(['serial_to' => 0]);
        $this->as()->postJson('/api/v1/cutting/bundles', $b)->assertUnprocessable()->assertJsonValidationErrors('serial_to');
        $this->as()->postJson('/api/v1/cutting/bundles', $this->card(['colour' => 'Green']))->assertUnprocessable()->assertJsonPath('errors.colour.0', 'Color Green is not on PO 11565705.');
        $this->as()->postJson('/api/v1/cutting/bundles', $this->card(['parts_name' => '']))->assertUnprocessable()->assertJsonPath('errors.parts_name.0', 'Parts Name is required.');

        $first = $this->as()->postJson('/api/v1/cutting/bundles', $this->card(['colour' => 'navy', 'size' => 's', 'batch_no' => 'b-7095']))->assertCreated()
            ->assertJsonPath('data.bundle_no', "BND-{$this->day}-0001")->assertJsonPath('data.barcode', "BND{$this->day}0001")->assertJsonPath('data.qty', 36)
            ->assertJsonPath('data.colour', 'Navy')->assertJsonPath('data.size', 'S')->assertJsonPath('data.batch_no', 'B-7095')->assertJsonPath('data.po_no', '11565705')
            ->assertJsonPath('data.style', '868MI')->assertJsonPath('data.buyer', 'LPP')->assertJsonPath('data.status', 'CREATED')->assertJsonPath('data.cutting_status', 'REGULAR')->json('data');
        $this->as()->postJson('/api/v1/cutting/bundles', $this->card(['serial_from' => 30, 'serial_to' => 50]))->assertUnprocessable()
            ->assertJsonPath('errors.serial_from.0', "Serials 1–36 of size S (Back Panel) are on BND-{$this->day}-0001 already.");
        // Another part of the same garment carries the same serials.
        $this->as()->postJson('/api/v1/cutting/bundles', $this->card(['parts_name' => 'Front Panel']))->assertCreated()->assertJsonPath('data.bundle_no', "BND-{$this->day}-0002");
        $this->as()->putJson("/api/v1/cutting/bundles/{$first['bundle_no']}", $this->card(['serial_to' => 30]))->assertOk()->assertJsonPath('data.qty', 30)->assertJsonPath('data.bundle_no', $first['bundle_no']);
        $this->as()->getJson('/api/v1/cutting/bundles?q=25')->assertJsonPath('data.bundles', 2);
        $this->as()->getJson('/api/v1/cutting/serial?serial=12&order_id='.$this->order.'&size=S')->assertJsonCount(2, 'data');
    }

    public function test_what_is_cut_beyond_the_colours_quantity_is_additional(): void
    {
        $this->as()->postJson('/api/v1/cutting/bundles', $this->card(['serial_from' => 1, 'serial_to' => 60]))->assertCreated()->assertJsonPath('data.cutting_status', 'REGULAR');
        $this->as()->postJson('/api/v1/cutting/bundles', $this->card(['size' => 'M', 'serial_from' => 61, 'serial_to' => 120]))->assertCreated()
            ->assertJsonPath('data.cutting_status', 'PARTIAL_ADDITIONAL')->assertJsonPath('data.additional_qty', 20);
        $this->as()->postJson('/api/v1/cutting/bundles', $this->card(['size' => 'M', 'serial_from' => 121, 'serial_to' => 130]))->assertCreated()
            ->assertJsonPath('data.cutting_status', 'ADDITIONAL')->assertJsonPath('data.additional_qty', 10);
        $this->as()->postJson('/api/v1/cutting/bundles', $this->card(['parts_name' => 'Front Panel', 'serial_from' => 1, 'serial_to' => 100]))->assertCreated()->assertJsonPath('data.cutting_status', 'REGULAR');
        $this->as()->postJson('/api/v1/cutting/bundles', $this->card(['colour' => 'Red', 'size' => 'M', 'serial_from' => 1, 'serial_to' => 25]))->assertCreated();

        $list = collect($this->as()->getJson('/api/v1/cutting/cutting-list')->assertOk()->json('data'));
        $navy = $list->firstWhere('colour', 'Navy');
        $this->assertEquals([100, 130, 0, 30, 30.0, 4], [$navy['order_qty'], $navy['already_cut'], $navy['balance'], $navy['additional'], $navy['additional_pct'], $navy['bundles']]);
        $this->assertSame(35, $list->firstWhere('colour', 'Red')['balance']);
        $this->as()->getJson('/api/v1/cutting/bundles?cutting_status=ANY_ADDITIONAL')->assertJsonPath('data.bundles', 2)->assertJsonPath('data.additional', 30);
    }

    public function test_cards_are_printed_reprinted_and_cancelled(): void
    {
        $a = $this->as()->postJson('/api/v1/cutting/bundles', $this->card())->json('data.bundle_no');
        $b = $this->as()->postJson('/api/v1/cutting/bundles', $this->card(['serial_from' => 37, 'serial_to' => 70]))->json('data.bundle_no');
        $this->as()->getJson('/api/v1/cutting/dashboard')->assertJsonPath('data.pending_print', 2)->assertJsonPath('data.today_cut', 70)->assertJsonPath('data.today_bundles', 2)->assertJsonPath('data.balance', 30);

        $this->as()->postJson('/api/v1/cutting/bundles/print', ['bundles' => []])->assertUnprocessable()->assertJsonPath('errors.bundles.0', 'Pick the cards to print.');
        $this->as()->postJson('/api/v1/cutting/bundles/print', ['bundles' => [$a], 'reprint' => true])->assertUnprocessable()->assertJsonPath('errors.bundles.0', "{$a} has not been printed yet — print it first.");
        $this->as()->postJson('/api/v1/cutting/bundles/print', ['bundles' => [$a, $b]])->assertOk()->assertJsonCount(2, 'data.cards')->assertJsonPath('data.cards.0.status', 'PRINTED')->assertJsonPath('data.cards.0.print_count', 1);
        $this->as()->postJson('/api/v1/cutting/bundles/print', ['bundles' => [$a]])->assertUnprocessable()->assertJsonPath('errors.bundles.0', "{$a} is printed already — reprint it instead.");
        $this->as()->postJson('/api/v1/cutting/bundles/print', ['bundles' => [$a], 'reprint' => true])->assertOk()->assertJsonPath('data.cards.0.status', 'REPRINTED')->assertJsonPath('data.cards.0.print_count', 2);
        $this->as()->getJson('/api/v1/cutting/dashboard')->assertJsonPath('data.pending_print', 0)->assertJsonPath('data.printed', 2)->assertJsonPath('data.latest.0.bundle_no', $b);

        $this->as()->postJson("/api/v1/cutting/bundles/{$b}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->as()->postJson("/api/v1/cutting/bundles/{$b}/cancel")->assertUnprocessable();
        $this->as()->putJson("/api/v1/cutting/bundles/{$b}", $this->card())->assertUnprocessable()->assertJsonPath('errors.bundle_no.0', "{$b} is cancelled.");
        $this->as()->getJson('/api/v1/cutting/cutting-list')->assertJsonPath('data.0.already_cut', 36)->assertJsonPath('data.0.bundles', 1);
        // Its serials are free again.
        $this->as()->postJson('/api/v1/cutting/bundles', $this->card(['serial_from' => 37, 'serial_to' => 70]))->assertCreated();
        $this->as()->getJson('/api/v1/cutting/bundles?status=CANCELLED')->assertJsonCount(1, 'data.rows');
    }

    public function test_a_cutting_is_laid_its_output_recorded_and_its_cards_cut_from_that_output(): void
    {
        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $in = ['order_id' => $this->order, 'colour' => 'navy', 'cutting_no' => 1, 'parts_name' => 'Back Panel', 'batch_no' => '7095', 'gsm' => '160', 'dia_width' => '72',
            'marker_length' => 4.5, 'plies' => 10, 'sizes' => [['size' => 's', 'ratio' => 2], ['size' => 'M', 'ratio' => 3]]];

        // The cutting master lays it; the line chief (output and cards only) may not.
        $this->withToken($chief)->postJson('/api/v1/cutting/inputs', $in)->assertForbidden();
        $this->as()->postJson('/api/v1/cutting/inputs', ['marker_length' => null] + $in)->assertUnprocessable()->assertJsonValidationErrors('lay_len');
        // Fabric by length (2026-11-16), in yards: 4.5 yds × 10 plies = 45 yds (41.148 m); × 160 GSM × 72" (1.8288 m) = 12.04 kg.
        // No fabric issued to cutting yet, so it is short by all 45 yds — saved anyway.
        $laid = $this->as()->postJson('/api/v1/cutting/inputs', $in)->assertCreated()->json('data');
        $this->assertEquals(['INPUT', 'Navy', 50, 4.5, 45.0, 12.04, 45.0, 'YDS'], [$laid['status'], $laid['colour'], $laid['planned_qty'], $laid['marker_length'], $laid['lay_len'], $laid['fabric_kg'], $laid['fabric_short_len'], $laid['len_unit']]);
        $this->assertSame([['S', 2, 20], ['M', 3, 30]], array_map(fn ($s) => [$s['size'], $s['ratio'], $s['planned_qty']], $laid['sizes']));
        $no = $laid['input_no'];
        $this->as()->postJson('/api/v1/cutting/inputs', $in)->assertUnprocessable()->assertJsonValidationErrors('cutting_no');

        $ctx = $this->as()->getJson("/api/v1/cutting/context?order_id={$this->order}&colour=Navy")->json('data');
        $this->assertEquals([2, 45.0, -45.0, 12.04], [$ctx['next_cutting_no'], $ctx['fabric']['used_len'], $ctx['fabric']['available_len'], $ctx['fabric']['used_kg']]);

        // No cards before the output; the output clerk records it.
        $this->withToken($chief)->postJson("/api/v1/cutting/inputs/{$no}/bundles", ['pcs_per_bundle' => 10, 'sizes' => [['size' => 'S', 'qty' => 10]]])->assertUnprocessable()->assertJsonValidationErrors('status');
        $out = ['sizes' => [['size' => 'S', 'output_qty' => 19, 'reject_qty' => 1], ['size' => 'M', 'output_qty' => 30]], 'used_len' => 44, 'remnant_len' => 0.5];
        $this->withToken($this->token('STORE_MANAGER', 'store@delta.test'))->putJson("/api/v1/cutting/inputs/{$no}/output", $out)->assertForbidden();
        $done = $this->withToken($chief)->putJson("/api/v1/cutting/inputs/{$no}/output", $out)->assertOk()->json('data');
        $this->assertEquals(['OUTPUT', 49, 1, 44.0, 0.5], [$done['status'], $done['output_qty'], $done['reject_qty'], $done['used_len'], $done['remnant_len']]);
        // Its kilos follow the lay's own: 44 yds at 12.04 kg / 45 yds.
        $this->assertEqualsWithDelta(11.772, $done['used_kg'], 0.001);
        // Once output, the cutting master's lay is fixed; the fabric used is what the output says.
        $this->as()->putJson("/api/v1/cutting/inputs/{$no}", $in)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertEquals(44.0, $this->as()->getJson("/api/v1/cutting/context?order_id={$this->order}&colour=Navy")->json('data.fabric.used_len'));

        // Cards from the output: S 19 → 1–10, 11–19; M 30 → three of ten.
        $made = $this->withToken($chief)->postJson("/api/v1/cutting/inputs/{$no}/bundles", ['pcs_per_bundle' => 10, 'sizes' => [['size' => 'S', 'qty' => 19], ['size' => 'M', 'qty' => 30]]])->assertCreated()->json('data');
        $this->assertSame([5, 49, 49], [$made['count'], $made['qty'], $made['input']['bundled_qty']]);
        $this->assertSame([['S', 1, 10], ['S', 11, 19], ['M', 1, 10], ['M', 11, 20], ['M', 21, 30]], array_map(fn ($b) => [$b['size'], $b['serial_from'], $b['serial_to']], $made['bundles']));
        $this->assertSame(5, DB::table('cut_bundles')->where('input_id', DB::table('cut_inputs')->where('input_no', $no)->value('id'))->where('cutting_no', 1)->where('batch_no', '7095')->count());

        // Past the output it asks first, and makes them when confirmed.
        $more = ['pcs_per_bundle' => 10, 'sizes' => [['size' => 'S', 'qty' => 5]]];
        $this->withToken($chief)->postJson("/api/v1/cutting/inputs/{$no}/bundles", $more)->assertUnprocessable()->assertJsonValidationErrors('confirm_over')->assertJsonPath('errors.confirm_over.0', 'More than the output: S +5. Make them anyway?');
        $this->withToken($chief)->postJson("/api/v1/cutting/inputs/{$no}/bundles", ['confirm_over' => true] + $more)->assertCreated()->assertJsonPath('data.bundles.0.serial_from', 20);

        // A cutting with cards is not cancelled; one without is.
        $this->as()->postJson("/api/v1/cutting/inputs/{$no}/cancel")->assertUnprocessable();
        $second = $this->as()->postJson('/api/v1/cutting/inputs', ['cutting_no' => 2, 'fabric_kg' => 8] + $in)->assertCreated()->json('data.input_no');
        $this->as()->postJson("/api/v1/cutting/inputs/{$second}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertSame([$no], array_column($this->as()->getJson("/api/v1/cutting/inputs?order_id={$this->order}&colour=Navy")->json('data'), 'input_no'));
    }

    public function test_who_may_cut(): void
    {
        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $this->withToken($chief)->postJson('/api/v1/cutting/bundles', $this->card())->assertCreated();
        $store = $this->token('STORE_MANAGER', 'store@delta.test');
        $this->withToken($store)->getJson('/api/v1/cutting/cutting-list')->assertOk();
        $this->withToken($store)->postJson('/api/v1/cutting/bundles', $this->card(['serial_from' => 100, 'serial_to' => 110]))->assertForbidden();
        $technician = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($technician)->getJson('/api/v1/cutting/dashboard')->assertForbidden();
    }

    /** @param array<string, mixed> $over @return array<string, mixed> */
    private function card(array $over = []): array
    {
        return $over + ['order_id' => $this->order, 'colour' => 'Navy', 'fabric_type' => 'Single Jersey', 'gsm' => '160', 'dia_width' => '72', 'cutting_no' => 5, 'size' => 'S', 'serial_from' => 1, 'serial_to' => 36, 'parts_name' => 'Back Panel', 'batch_no' => '7095'];
    }

    private function token(string $role, string $email): string
    {
        return app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, $role, $email), $this->delta->id, 'Test token')['plain'];
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
