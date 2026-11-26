<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OrderTna;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\TnaTask;
use App\Modules\Merchandise\Models\TnaTaskGroup;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Merchandise Desk → the AI groups (AnnotechERP 06/02–06/04): Run analysis
 * scores every live order from schedule pressure, material, production and
 * approvals into a composite risk and band, a health score, a delay forecast
 * and the next move; ranks the open T&A tasks; raises alerts that keep the
 * status a user gave them across runs; and works without a Claude key.
 */
class MerchandiseAiEngineTest extends TestCase
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

    public function test_run_analysis_scores_the_live_book_ranks_tasks_and_keeps_alert_status(): void
    {
        $today = CarbonImmutable::today();
        [$tight, $roomy] = $this->orders($today);
        $this->as()->getJson('/api/v1/merchandise/ai/snapshot')->assertOk()->assertJsonPath('data.run', null)->assertJsonPath('data.claude', false);

        // One task already late on the tight order, one closing milestone on the roomy one.
        $group = TnaTaskGroup::query()->create(['code' => 'MER', 'name' => 'Merchandising', 'active' => true]);
        $fit = TnaTask::query()->create(['tna_task_group_id' => $group->id, 'code' => 'FIT-APP', 'sort_order' => 10, 'name' => 'Fit Sample Approval', 'is_sample' => true, 'active' => true]);
        $ship = TnaTask::query()->create(['tna_task_group_id' => $group->id, 'code' => 'EX-FTY', 'sort_order' => 20, 'name' => 'Ex-Factory', 'is_end' => true, 'active' => true]);
        $tightId = Order::query()->where('order_no', $tight)->value('id');
        $roomyId = Order::query()->where('order_no', $roomy)->value('id');
        OrderTna::query()->create(['order_id' => $tightId, 'tna_task_id' => $fit->id, 'plan_date' => $today->subDays(12)->toDateString(), 'required' => true, 'sort_order' => 1]);
        OrderTna::query()->create(['order_id' => $roomyId, 'tna_task_id' => $ship->id, 'plan_date' => $today->addDays(78)->toDateString(), 'required' => true, 'sort_order' => 1]);

        $snap = $this->as()->postJson('/api/v1/merchandise/ai/run')->assertOk()->json('data');
        $this->assertSame(['RULE', 2, 2], [$snap['run']['engine'], $snap['run']['orders'], $snap['run']['tasks']]);
        [$first, $second] = $snap['orders'];
        // 100 of 110 days gone, nothing made, no material: schedule, material and production all near the top.
        $this->assertSame([$tight, 'CRITICAL'], [$first['order_no'], $first['band']]);
        $this->assertGreaterThanOrEqual(75, $first['risk']);
        $this->assertGreaterThan(80, $first['delay_probability']);
        $this->assertGreaterThan(0, $first['expected_delay']);
        $this->assertStringStartsWith("The delivery date on {$tight} is exposed: 10 days to ship with 0% made against 91% of the window.", $first['action']);
        $this->assertSame('no approved fabric booking, no approved accessories booking, no purchase order placed, nothing received into store', $first['factors']['material']);
        $this->assertSame('1 of 1 approvals outstanding', $first['factors']['approval']);
        $this->assertSame([$roomy, 'LOW'], [$second['order_no'], $second['band']]);
        $this->assertStringStartsWith("Chase the material chain on {$roomy}", $second['action']);
        // Nothing made and no task closed: health is the other side of risk alone.
        $this->assertEqualsWithDelta(0.6 * (100 - $first['risk']), $first['health'], 0.11);
        // The composite is the five weighted factors, as the Risk Driver Analysis adds them up.
        $weights = $snap['weights'];
        $this->assertEqualsWithDelta(1.0, array_sum($weights), 0.0001);
        $this->assertEqualsWithDelta($first['risk'], array_sum(array_map(fn (string $f) => $weights[$f] * $first[$f.'_risk'], array_keys($weights))), 0.11);
        // No buyer-approval milestone on the roomy order is a gap, not a clean sheet.
        $this->assertEquals(30.0, $second['approval_risk']);

        $this->assertSame(['Fit Sample Approval', 12, 15, 'Merchandising', "12 days past plan on {$tight}. Close it today or re-plan the date — the forecast has it slipping 15 days if nothing changes."],
            [$snap['tasks'][0]['task'], $snap['tasks'][0]['days_late'], $snap['tasks'][0]['slip_days'], $snap['tasks'][0]['department'], $snap['tasks'][0]['recommendation']]);
        $this->assertSame(['Ex-Factory', true], [$snap['tasks'][1]['task'], $snap['tasks'][1]['milestone']]);
        // A late task on the critical order carries that order's delay probability and outranks the far-off milestone.
        $this->assertEquals($first['delay_probability'], $snap['tasks'][0]['slip_risk']);
        $this->assertGreaterThan($snap['tasks'][1]['priority'], $snap['tasks'][0]['priority']);
        $this->assertEquals(['live' => 2, 'at_risk' => 1, 'pending' => 2, 'overdue' => 1, 'value' => 3000], array_intersect_key($snap['totals'], array_flip(['live', 'at_risk', 'pending', 'overdue', 'value'])));
        $this->assertSame([['LPP', 2, 1]], array_map(fn ($b) => [$b['buyer'], $b['orders'], $b['at_risk']], $snap['buyers']));
        $this->assertSame([['Merchandising', 2, 1]], array_map(fn ($d) => [$d['name'], $d['tasks'], $d['overdue']], $snap['departments']));
        $this->assertContains(['SHIPMENT', $tight], array_map(fn ($d) => [$d['kind'], $d['order_no']], $snap['deadlines']));

        // Alerts on the tight order only: shipping soon, material, a task late, an approval out.
        $kinds = collect($snap['alerts'])->where('order_no', $tight)->pluck('kind')->sort()->values()->all();
        $this->assertSame(['APPROVAL', 'MATERIAL', 'SHIP_SOON', 'TASKS'], $kinds);
        $this->assertSame([], collect($snap['alerts'])->where('order_no', $roomy)->all());
        $alerts = collect($snap['alerts'])->keyBy('kind');
        $this->as()->postJson("/api/v1/merchandise/ai/alerts/{$alerts['SHIP_SOON']['id']}/acknowledge")->assertOk()->assertJsonPath('data.status', 'ACKNOWLEDGED');
        $this->as()->postJson("/api/v1/merchandise/ai/alerts/{$alerts['APPROVAL']['id']}/dismiss")->assertOk();
        $this->as()->postJson("/api/v1/merchandise/ai/alerts/{$alerts['MATERIAL']['id']}/resolve")->assertOk();

        // The task is closed; the next run resolves its alert, keeps the acknowledged and dismissed ones, and reopens a "resolved" one whose reason is still there.
        OrderTna::query()->where('order_id', $tightId)->update(['actual_date' => $today->toDateString()]);
        $again = collect($this->as()->postJson('/api/v1/merchandise/ai/run')->assertOk()->json('data.alerts'))->where('order_no', $tight)->pluck('status', 'kind')->all();
        $this->assertSame(['ACKNOWLEDGED', 'OPEN', 'RESOLVED'], [$again['SHIP_SOON'], $again['MATERIAL'], $again['TASKS']]);
        $this->assertSame('DISMISSED', $again['APPROVAL']);
        $this->as()->postJson("/api/v1/merchandise/ai/alerts/{$alerts['MATERIAL']['id']}/archive")->assertNotFound();

        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->getJson('/api/v1/merchandise/ai/snapshot')->assertOk();
        $this->as($manager)->postJson('/api/v1/merchandise/ai/run')->assertForbidden();
        $store = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($store)->getJson('/api/v1/merchandise/ai/snapshot')->assertForbidden();
    }

    /** Two confirmed orders for LPP: one ten days from shipping, 100 days into its window; one eighty days out, ten days in. */
    private function orders(CarbonImmutable $today): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $make = function (string $job, int $qty, int $confAgo, int $shipIn) use ($style, $today): string {
            $id = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => $job, 'style_id' => $style, 'total_qty' => $qty, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');
            Order::query()->whereKey($id)->update(['status' => 'CONFIRMED', 'conf_date' => $today->subDays($confAgo)->toDateString(), 'ship_date' => $today->addDays($shipIn)->toDateString()]);

            return Order::query()->whereKey($id)->value('order_no');
        };

        return [$make('529', 1000, 100, 10), $make('530', 500, 10, 80)];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
