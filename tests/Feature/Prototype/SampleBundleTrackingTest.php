<?php

declare(strict_types=1);

namespace Tests\Feature\Prototype;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Prototype\Models\Bundle;
use App\Modules\Prototype\Models\BundleMovement;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\ProductionLine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Prototype Desk → Sample Bundle Tracking (AnnotechERP 07/03): a finalized
 * card's bundles scanned through the sample room's processes — once each,
 * print and embroidery exclusive, received only once sent, sewn only once
 * issued to sewing — the card going into production and, all distributed,
 * completed; Undo until the bundle moves on; each movement's timing and
 * delay recorded; and the order's sample cards followed to delivery.
 */
class SampleBundleTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK')->id;
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_bundles_are_scanned_through_the_processes_and_the_card_follows(): void
    {
        $card = $this->issuedCard();
        $scan = fn (string $process, string $code) => $this->as()->postJson('/api/v1/prototype/tracking/scan', ['process' => $process, 'code' => $code]);
        [$one, $two] = ['SBC-2026100001-01', 'SBC-2026100001-02'];

        $scan('DELV_PRINT', 'SBC-0000')->assertUnprocessable()->assertJsonPath('errors.code.0', 'No bundle carries the code SBC-0000.');
        // Out for embroidery: not also for print; received back only once sent.
        $scan('RECV_EMBELLISHMENT', $one)->assertUnprocessable()->assertJsonPath('errors.code.0', "{$one} was never sent out — only bundles that were sent can be received.");
        $moved = $scan('DELV_EMBR', strtolower($one))->assertCreated()->json('data');
        $this->assertSame(['DELV_EMBR', $one, 2, 'RECEIVED'], [$moved['process'], $moved['bundle_no'], $moved['qty'], $moved['status']]);
        $this->assertSame('IN_PRODUCTION', $this->as()->getJson("/api/v1/prototype/bundle-cards/{$card}")->json('data.bundle_status'));
        $scan('DELV_EMBR', $one)->assertUnprocessable()->assertJsonPath('errors.code.0', "{$one} is already scanned into Delv. For Embr.");
        $scan('DELV_PRINT', $one)->assertUnprocessable()->assertJsonPath('errors.code.0', "{$one} was sent to Delv. For Embr, so it cannot go this way too.");
        $scan('DELV_SEWING', $one)->assertUnprocessable()->assertJsonPath('errors.code.0', "{$one} is still out at the embellishment house — receive it first.");
        $scan('SEWING_DONE', $two)->assertUnprocessable()->assertJsonPath('errors.code.0', "{$two} has not been issued to sewing.");

        // Undo withdraws the latest movement only.
        $recv = $scan('RECV_EMBELLISHMENT', $one)->assertCreated()->json('data.id');
        $this->as()->deleteJson("/api/v1/prototype/tracking/movements/{$moved['id']}")->assertUnprocessable()->assertJsonPath('errors.movement.0', 'This bundle has already moved on; the movement cannot be withdrawn.');
        $this->as()->deleteJson("/api/v1/prototype/tracking/movements/{$recv}")->assertOk();
        $this->assertSame('DELV_EMBR', Bundle::query()->where('bundle_no', $one)->value('now_at'));
        $scan('RECV_EMBELLISHMENT', $one)->assertCreated();

        // Today's desk at a process, and each process's count.
        $desk = $this->as()->getJson('/api/v1/prototype/tracking/desk/RECV_EMBELLISHMENT')->assertOk()->json('data');
        $this->assertSame([[$one], 1, 1], [array_column($desk['movements'], 'bundle_no'), $desk['counts']['DELV_EMBR'], $desk['counts']['RECV_EMBELLISHMENT']]);

        foreach ([$one, $two] as $code) {
            $scan('DELV_SEWING', $code)->assertCreated();
            $scan('SEWING_DONE', $code)->assertCreated();
        }
        $scan('DISTRIBUTION', $one)->assertCreated();
        $this->assertSame('IN_PRODUCTION', $this->as()->getJson("/api/v1/prototype/bundle-cards/{$card}")->json('data.bundle_status'));
        $last = $scan('DISTRIBUTION', $two)->assertCreated()->json('data.id');
        $this->assertSame('COMPLETED', $this->as()->getJson("/api/v1/prototype/bundle-cards/{$card}")->json('data.bundle_status'));
        // Withdrawing the last distribution puts the card back in production; a completed card scans nothing more.
        $this->as()->deleteJson("/api/v1/prototype/tracking/movements/{$last}")->assertOk();
        $this->assertSame('IN_PRODUCTION', $this->as()->getJson("/api/v1/prototype/bundle-cards/{$card}")->json('data.bundle_status'));
        $scan('DISTRIBUTION', $two)->assertCreated();
        $scan('DELV_PRINT', $two)->assertUnprocessable()->assertJsonPath('errors.code.0', 'Card SBC-2026100001 is Completed — only a finalized card whose bundles are on the floor is scanned.');

        // Tracking Update: times, expected hours, the delay and its reason.
        $sewing = BundleMovement::query()->where('process', 'DELV_SEWING')->where('bundle_id', Bundle::query()->where('bundle_no', $one)->value('id'))->firstOrFail()->id;
        $times = ['status' => 'COMPLETED', 'operator_id' => $this->owner->id, 'start_at' => '2026-10-22 08:00', 'end_at' => '2026-10-22 13:00', 'expected_hours' => 4];
        $this->as()->putJson("/api/v1/prototype/tracking/movements/{$sewing}", array_merge($times, ['end_at' => '2026-10-22 07:00']))->assertUnprocessable()->assertJsonPath('errors.end_at.0', 'End cannot be before Start.');
        $this->as()->putJson("/api/v1/prototype/tracking/movements/{$sewing}", $times)->assertUnprocessable()->assertJsonPath('errors.delay_reason.0', 'A delayed movement needs its Delay Reason.');
        $updated = $this->as()->putJson("/api/v1/prototype/tracking/movements/{$sewing}", $times + ['delay_reason' => 'Machine down'])->assertOk()->json('data');
        $this->assertEquals([1.0, true, $this->owner->name], [$updated['delay_hours'], $updated['delayed'], $updated['operator_name']]);

        // Records, the moved bundles, the lookup.
        // The first bundle went out for embroidery and back (5 movements); the second straight to sewing (3).
        $this->assertCount(8, $this->as()->getJson('/api/v1/prototype/tracking')->assertOk()->json('data'));
        $this->assertSame([5, 3], array_map(fn ($b) => count($b['movements']), $this->as()->getJson('/api/v1/prototype/tracking/bundles')->assertOk()->json('data')));
        $lookup = collect($this->as()->getJson('/api/v1/prototype/tracking/lookup')->assertOk()->json('data'))->keyBy('bundle_no');
        $this->assertSame(['DISTRIBUTION', true, 'COMPLETED'], [$lookup[$one]['now_at'], $lookup[$one]['delayed'], $lookup[$one]['bundle_status']]);

        // Sample Card: sewn 5; delivered and rejected within it, or the whole card handed over.
        $cards = $this->as()->getJson('/api/v1/prototype/tracking/sample-cards')->assertOk()->json('data');
        $this->assertSame([5, 5, 0], [$cards[0]['cards'][0]['sewn_qty'], $cards[0]['cards'][0]['out_qty'], $cards[0]['cards'][0]['delivered_qty']]);
        $this->as()->putJson("/api/v1/prototype/tracking/sample-cards/{$card}", ['delivered_qty' => 5, 'rejected_qty' => 1])->assertUnprocessable()
            ->assertJsonPath('errors.delivered_qty.0', 'Delivered and rejected together cannot be more than the 5 sewn.');
        $this->as()->putJson("/api/v1/prototype/tracking/sample-cards/{$card}", ['rejected_qty' => 1, 'hand_over' => true])->assertOk()->assertJsonPath('data.delivered_qty', 4);

        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->as($technician)->getJson('/api/v1/prototype/tracking')->assertForbidden();
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->getJson('/api/v1/prototype/tracking')->assertOk();
        $scan2 = $this->as($manager)->postJson('/api/v1/prototype/tracking/scan', ['process' => 'DELV_PRINT', 'code' => $one]);
        $scan2->assertForbidden();
    }

    public function test_only_an_issued_card_is_scanned(): void
    {
        $card = $this->issuedCard(issue: false);
        $this->as()->postJson('/api/v1/prototype/tracking/scan', ['process' => 'DELV_SEWING', 'code' => "{$card}-01"])->assertUnprocessable()
            ->assertJsonPath('errors.code.0', "Card {$card} is Created — only a finalized card whose bundles are on the floor is scanned.");
        $this->as()->postJson("/api/v1/prototype/bundle-cards/{$card}/issue")->assertOk();
        $this->as()->postJson('/api/v1/prototype/tracking/scan', ['process' => 'DELV_SEWING', 'code' => "{$card}-01"])->assertCreated();
        // Once a bundle has been scanned the card is no longer re-bundled.
        $this->assertFalse($this->as()->getJson("/api/v1/prototype/bundle-cards/{$card}")->json('data.rebundle'));
    }

    /** A sample's bundle card of two bundles (2 + 3 cut) on a cutting table, issued. */
    private function issuedCard(bool $issue = true): string
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '616'], ['name' => 'Poland', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => 'SS27-TEE01', 'description' => 'Summer Set', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 1000, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');
        $type = collect($this->as()->getJson('/api/v1/prototype/requisitions/options')->json('data.sample_types'))->firstWhere('label', 'Fit Sample (FIT)');
        $this->as()->postJson('/api/v1/prototype/requisitions', ['req_date' => '2026-10-10', 'buyer_id' => $buyer, 'style_id' => $style, 'sample_type_id' => $type['value'], 'qty' => 1, 'required_date' => '2026-10-30'])->assertCreated();
        $this->as()->postJson('/api/v1/prototype/requisitions/SR-2026-00001/submit')->assertOk();
        foreach (range(1, 3) as $i) {
            $this->as()->postJson('/api/v1/prototype/requisitions/SR-2026-00001/decide', ['decision' => 'APPROVE'])->assertOk();
        }
        $cutting = Department::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit, 'name' => 'Cutting', 'code' => 'DPT-CUT']);
        $table = ProductionLine::query()->create(['company_id' => $this->delta->id, 'department_id' => $cutting->id, 'code' => 'C1', 'name' => 'Cutting 1', 'line_type' => 'CUTTING', 'status' => 'ACTIVE'])->id;
        $card = $this->as()->postJson('/api/v1/prototype/bundle-cards', [
            'sample_id' => Sample::query()->value('id'), 'buyer_id' => $buyer, 'style_id' => $style, 'order_id' => $order, 'cutting_no' => '7', 'table_id' => $table, 'cutting_date' => '2026-10-22',
            'bundles' => [['order_id' => $order, 'color' => 'Navy', 'size' => 'M', 'req_qty' => 2, 'cutting_qty' => 2], ['order_id' => $order, 'color' => 'Navy', 'size' => 'L', 'req_qty' => 2, 'cutting_qty' => 3]],
        ])->assertCreated()->json('data.card_no');
        if ($issue) {
            $this->as()->postJson("/api/v1/prototype/bundle-cards/{$card}/issue")->assertOk();
        }

        return $card;
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
