<?php

declare(strict_types=1);

namespace Tests\Feature\FabricPlanning;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Fabric Requirement → Operations (AnnotechERP 13/02): the constraint ruleset
 * as Brickland ships it, its areas and score bands edited in place; closed
 * days per section; machine capacity by week against the batch plans; batch
 * plans with fabric lines; and scheduling boards with batches in running
 * order — and who may change them.
 */
class FabricPlanningOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $unit;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        CarbonImmutable::setTestNow('2026-10-25 10:00:00');
        $this->travelTo(CarbonImmutable::parse('2026-10-25 10:00:00'));
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_the_ruleset_starts_as_brickland_ships_it_and_is_edited_in_place(): void
    {
        $records = $this->as()->getJson('/api/v1/erp/fp-status-areas')->assertOk()->json('data');
        $this->assertCount(16, $records);
        $this->assertSame(['1. Critical Date', 1, 'CD-SHIP', 'Days to shipment', 1.0], [$records[0]['group'], $records[0]['sl'], $records[0]['code'], $records[0]['name'], (float) $records[0]['wt_factor']]);
        $groups = $this->as()->getJson('/api/v1/fabric-planning/status-groups')->json('data');
        $this->assertSame([['1. Critical Date', 3, 5], ['3. Lead Time', 2, 5]], [[$groups[0]['label'], $groups[0]['areas'], $groups[0]['rules']], [$groups[2]['label'], $groups[2]['areas'], $groups[2]['rules']]]);
        $rules = $this->as()->getJson('/api/v1/fabric-planning/status-groups/1/rules')->json('data');
        $this->assertSame([['CRITICAL', '#DC2626', 'POOR'], ['FAIR', '#CA8A04', 'WATCH'], ['EXCELLENT', '#15803D', 'GOOD']], [[$rules[0]['status_code'], $rules[0]['color_code'], $rules[0]['standing']], [$rules[2]['status_code'], $rules[2]['color_code'], $rules[2]['standing']], [$rules[4]['status_code'], $rules[4]['color_code'], $rules[4]['standing']]]);

        // New plan status area: Brickland's message without a group; codes are unique and fixed once made.
        $this->as()->postJson('/api/v1/erp/fp-status-areas', ['sl' => 4, 'code' => 'cd-x', 'name' => 'X', 'wt_factor' => 1])->assertUnprocessable()->assertJsonPath('errors.group_id.0', 'Status Group is required.');
        $area = $this->as()->postJson('/api/v1/erp/fp-status-areas', ['group_id' => $groups[0]['id'], 'sl' => 4, 'code' => 'cd-ex', 'name' => 'Ex-factory moved', 'wt_factor' => 0.5])->assertCreated()->assertJsonPath('data.code', 'CD-EX')->json('data');
        $this->as()->postJson('/api/v1/erp/fp-status-areas', ['group_id' => $groups[0]['id'], 'sl' => 5, 'code' => 'CD-EX', 'name' => 'Again', 'wt_factor' => 1])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->as()->patchJson("/api/v1/erp/fp-status-areas/{$area['id']}", ['code' => 'CD-NEW'])->assertUnprocessable()->assertJsonPath('errors.code.0', 'The code is fixed once created.');
        $ship = $records[0]['id'];
        $lead = $records[6]['id'];
        $this->as()->patchJson("/api/v1/erp/fp-status-areas/{$area['id']}", ['parent_id' => $lead])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->as()->patchJson("/api/v1/erp/fp-status-areas/{$area['id']}", ['parent_id' => $ship])->assertOk()->assertJsonPath('data.parent', 'CD-SHIP');

        // Areas in place: a parent cannot go while its child stays; the rest saves at once.
        $rows = $this->as()->getJson('/api/v1/fabric-planning/status-groups/1/areas')->json('data');
        $this->assertSame(['CD-SHIP', 'CD-PCD', 'CD-SLIP', 'CD-EX'], array_column($rows, 'code'));
        $keep = fn (array $codes) => array_values(array_filter($rows, fn ($r) => in_array($r['code'], $codes, true)));
        $this->as()->putJson('/api/v1/fabric-planning/status-groups/1/areas', ['rows' => $keep(['CD-PCD', 'CD-SLIP', 'CD-EX'])])->assertUnprocessable()->assertJsonValidationErrors('rows');
        $edited = $keep(['CD-SHIP', 'CD-PCD', 'CD-EX']);
        $edited[1]['wt_factor'] = 0.75;
        $edited[] = ['sl' => 9, 'code' => 'cd-rush', 'name' => 'Rush order', 'wt_factor' => 0.3, 'active' => false];
        $saved = $this->as()->putJson('/api/v1/fabric-planning/status-groups/1/areas', ['rows' => $edited])->assertOk()->json('data');
        $this->assertSame([['CD-SHIP', 1.0], ['CD-PCD', 0.75], ['CD-EX', 0.5], ['CD-RUSH', 0.3]], array_map(fn ($r) => [$r['code'], (float) $r['wt_factor']], $saved['rows']));
        $this->assertSame(4, $saved['groups'][0]['areas']);
        $this->as()->putJson('/api/v1/fabric-planning/status-groups/1/areas', ['rows' => [['code' => 'CD-PCD'] + $saved['rows'][0]]])->assertUnprocessable()->assertJsonValidationErrors(['rows.0.code' => 'The code is fixed once created.']);

        // Score bands may not overlap; a band's code is fixed.
        $bands = array_map(fn ($r) => array_intersect_key($r, array_flip(['id', 'score_from', 'score_to', 'status_code', 'status_desc', 'color_code', 'green_rank'])), $rules);
        $over = $bands;
        $over[1]['score_from'] = 15;
        $this->as()->putJson('/api/v1/fabric-planning/status-groups/1/rules', ['rows' => $over])->assertUnprocessable()->assertJsonValidationErrors(['rows.1.score_from' => 'This band overlaps CRITICAL.']);
        $bad = $bands;
        $bad[0]['color_code'] = 'red';
        $this->as()->putJson('/api/v1/fabric-planning/status-groups/1/rules', ['rows' => $bad])->assertUnprocessable()->assertJsonValidationErrors(['rows.0.color_code' => 'Give the colour as #RRGGBB.']);
        $four = array_slice($bands, 0, 4);
        $four[3]['score_to'] = 100;
        $four[3]['status_desc'] = 'Good or better';
        $done = $this->as()->putJson('/api/v1/fabric-planning/status-groups/1/rules', ['rows' => $four])->assertOk()->json('data');
        $this->assertSame([4, 'Good or better', 100.0], [count($done['rows']), $done['rows'][3]['status_desc'], (float) $done['rows'][3]['score_to']]);
        $this->as()->getJson('/api/v1/fabric-planning/status-groups/2/rules')->assertJsonCount(5, 'data');
    }

    public function test_closed_days_machine_capacity_batch_plans_and_boards(): void
    {
        Department::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit->id, 'name' => 'Cutting', 'code' => 'CUT']);
        Department::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit->id, 'name' => 'Knitting & Dyeing', 'code' => 'KND']);

        // New factory holiday: Brickland's message without the company; one row per unit, section and day.
        $holiday = ['company' => $this->delta->id, 'factory_id' => $this->unit->id, 'day' => '2026-12-16', 'day_type' => 'GOVT_HOLIDAY', 'name' => 'Victory Day'];
        $this->as()->postJson('/api/v1/erp/fp-holidays', ['company' => null] + $holiday)->assertUnprocessable()->assertJsonPath('errors.company.0', 'Company is required.');
        $this->as()->postJson('/api/v1/erp/fp-holidays', $holiday)->assertCreated()->assertJsonPath('data.section_id', null);
        $this->as()->postJson('/api/v1/erp/fp-holidays', $holiday)->assertUnprocessable()->assertJsonValidationErrors('day');

        // A section's closed days, saved in place.
        $sections = $this->as()->getJson('/api/v1/fabric-planning/sections')->json('data');
        $this->assertSame([['CUT', 'Cutting', 0], ['KND', 'Knitting & Dyeing', 0]], array_map(fn ($s) => [$s['key'], $s['label'], $s['count']], $sections));
        $days = [['factory_id' => $this->unit->id, 'day' => '2026-10-30', 'day_type' => 'WEEKLY_OFF'], ['factory_id' => $this->unit->id, 'day' => '2026-11-06', 'day_type' => 'WEEKLY_OFF', 'name' => 'Friday']];
        $this->as()->putJson('/api/v1/fabric-planning/sections/CUT/days', ['rows' => [$days[0], $days[0]]])->assertUnprocessable()->assertJsonValidationErrors('rows.1.day');
        $saved = $this->as()->putJson('/api/v1/fabric-planning/sections/CUT/days', ['rows' => $days])->assertOk()->json('data');
        $this->assertSame(['2026-10-30', '2026-11-06'], array_column($saved['rows'], 'day'));
        $this->assertSame(2, $saved['sections'][0]['count']);
        $this->as()->putJson('/api/v1/fabric-planning/sections/CUT/days', ['rows' => [$saved['rows'][1]]])->assertOk()->assertJsonCount(1, 'data.rows');
        $this->as()->getJson('/api/v1/erp/fp-holidays')->assertJsonCount(2, 'data');
        $this->as()->getJson('/api/v1/fabric-planning/sections/NOPE/days')->assertNotFound();

        // Machine capacity for a week; the week is the Saturday it starts on.
        $machine = $this->dyeMachine();
        $cap = ['dye_machine_id' => $machine, 'week_start' => '2026-10-28', 'capacity_kg' => 1500, 'working_days' => 6, 'holidays' => 1, 'max_load_pct' => 90, 'capacity_hours' => 144, 'avg_batch_qty' => 900];
        $this->as()->postJson('/api/v1/erp/fp-machine-capacities', ['capacity_kg' => 0] + $cap)->assertUnprocessable()->assertJsonPath('errors.capacity_kg.0', 'Capacity 100% must be greater than zero.');
        $this->as()->postJson('/api/v1/erp/fp-machine-capacities', ['holidays' => 2] + $cap)->assertUnprocessable()->assertJsonValidationErrors('holidays');
        $this->as()->postJson('/api/v1/erp/fp-machine-capacities', $cap)->assertCreated()->assertJsonPath('data.week_start', '2026-10-24')->assertJsonPath('data.week', '2026-W44 · 24-Oct → 30-Oct')->assertJsonPath('data.max_load_kg', 1350);
        $this->as()->postJson('/api/v1/erp/fp-machine-capacities', ['week_start' => '2026-10-24'] + $cap)->assertUnprocessable()->assertJsonValidationErrors('week_start');

        // Batch plan with fabric lines: BP-00001, its quantity the sum of the lines.
        $options = $this->as()->getJson('/api/v1/fabric-planning/options')->assertOk()->json('data');
        $this->assertContains(['value' => '2026-10-24', 'label' => '2026-W44 · 24-Oct → 30-Oct'], $options['weeks']);
        $group = $this->as()->getJson('/api/v1/erp/dye-colour-groups')->json('data.0.id');
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true]);
        $plan = ['week_start' => '2026-10-27', 'buyer_id' => $buyer->id, 'style_job' => '529', 'colour_type' => 'SOLID', 'colour_group_id' => $group, 'dyeing_method' => 'EXHAUST', 'dye_machine_id' => $machine, 'batches' => 2, 'status' => 'PLANNED'];
        $this->as()->postJson('/api/v1/fabric-planning/batch-plans', $plan + ['lines' => []])->assertUnprocessable()->assertJsonPath('errors.lines.0', 'A batch plan with no lines posts nothing, so at least one is required before this can be saved.');
        $this->as()->postJson('/api/v1/fabric-planning/batch-plans', ['colour_group_id' => null] + $plan + ['lines' => [['fabric' => 'S/J 160', 'bp_qty' => 10]]])->assertUnprocessable()->assertJsonPath('errors.colour_group_id.0', 'Colour Group is required.');
        $lines = [['fabric' => 'S/J 160 GSM Cream', 'grey_req' => 700, 'bp_qty' => 650], ['fabric' => '1x1 Rib Cream', 'grey_req' => 120, 'bp_plus' => 5, 'bp_qty' => 125]];
        $made = $this->as()->postJson('/api/v1/fabric-planning/batch-plans', $plan + ['bp_qty' => 99999, 'lines' => $lines])->assertCreated()->json('data');
        $this->assertSame(['BP-00001', '2026-10-24', 775.0, 820.0, [50.0, -5.0]], [$made['bp_no'], $made['week_start'], (float) $made['bp_qty'], (float) $made['grey_req'], array_map(fn ($l) => (float) $l['balance'], $made['lines'])]);

        // New batch plan (the New tab): no lines, a typed BP quantity.
        $this->as()->postJson('/api/v1/fabric-planning/batch-plans', ['week_start' => '2026-10-31', 'dyeing_method' => 'COLD_PAD_BATCH', 'batches' => 1, 'status' => 'DRAFT'])->assertUnprocessable()->assertJsonPath('errors.bp_qty.0', 'BP Qty must be greater than zero.');
        $this->as()->postJson('/api/v1/fabric-planning/batch-plans', ['week_start' => '2026-10-31', 'dyeing_method' => 'COLD_PAD_BATCH', 'dye_machine_id' => $machine, 'batches' => 1, 'bp_qty' => 400, 'status' => 'DRAFT'])->assertCreated()->assertJsonPath('data.bp_no', 'BP-00002');
        $this->as()->putJson('/api/v1/fabric-planning/batch-plans/bp-00001', ['batches' => 3] + $plan + ['lines' => [$lines[0]]])->assertOk()->assertJsonPath('data.bp_qty', 650);

        // The capacity board reads the plans of the week.
        $board = $this->as()->getJson('/api/v1/fabric-planning/capacity-weeks/2026-10-26')->assertOk()->json('data');
        $this->assertSame(['MFL-01', 3, 650.0, 700.0, 48], [$board['rows'][0]['machine'], $board['rows'][0]['planned_batches'], (float) $board['rows'][0]['planned_kg'], (float) $board['rows'][0]['free_kg'], $board['rows'][0]['load_pct']]);
        $this->assertSame([['2026-10-24', 1]], array_map(fn ($w) => [$w['key'], $w['count']], $this->as()->getJson('/api/v1/fabric-planning/capacity-weeks')->json('data')));

        // A scheduling board: SP-00001; batches in running order, once each.
        $sp = $this->as()->postJson('/api/v1/erp/fp-schedule-plans', ['name' => 'Week 44 dye floor', 'basis' => 'SHIPMENT', 'scale' => 'WEEK', 'cell_width' => 100])->assertCreated()->assertJsonPath('data.plan_no', 'SP-00001')->json('data');
        $this->as()->postJson('/api/v1/erp/fp-schedule-plans', ['name' => 'Bad', 'basis' => 'SHIPMENT', 'scale' => 'WEEK', 'cell_width' => 0])->assertUnprocessable()->assertJsonPath('errors.cell_width.0', 'Cell Width(%) must be greater than zero.');
        $this->as()->putJson('/api/v1/fabric-planning/schedule-plans/SP-00001/batches', ['rows' => [['bp_no' => 'BP-00001'], ['bp_no' => 'BP-00001']]])->assertUnprocessable()->assertJsonValidationErrors('rows.1.bp_no');
        $this->as()->putJson('/api/v1/fabric-planning/schedule-plans/SP-00001/batches', ['rows' => [['bp_no' => 'BP-00002', 'planned_start' => '2026-10-27', 'planned_end' => '2026-10-26']]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.planned_end');
        $placed = $this->as()->putJson('/api/v1/fabric-planning/schedule-plans/sp-00001/batches', ['rows' => [['bp_no' => 'BP-00002', 'planned_start' => '2026-10-26', 'planned_end' => '2026-10-26'], ['bp_no' => 'BP-00001']]])->assertOk()->json('data');
        $this->assertSame([['BP-00002', 1, 'MFL-01'], ['BP-00001', 2, 'MFL-01']], array_map(fn ($r) => [$r['bp_no'], $r['sequence'], $r['machine']], $placed['rows']));
        $this->assertSame(2, $this->as()->getJson("/api/v1/erp/fp-schedule-plans/{$sp['id']}")->json('data.batches'));

        // Who may: a store manager neither sees nor changes the plans.
        $store = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($store)->getJson('/api/v1/fabric-planning/batch-plans')->assertForbidden();
        $this->as($store)->postJson('/api/v1/erp/fp-schedule-plans', ['name' => 'X', 'basis' => 'SHIPMENT', 'scale' => 'DAY', 'cell_width' => 10])->assertForbidden();
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->getJson('/api/v1/fabric-planning/batch-plans')->assertOk()->assertJsonCount(2, 'data');
    }

    private function dyeMachine(): string
    {
        $lists = collect($this->as()->getJson('/api/v1/erp/dye-machine-lists')->json('data'));
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '156', 'name' => 'China'])->json('data.id');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit->id, 'name' => 'Dye', 'code' => 'B1']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Dye Floor', 'code' => 'DF-1']);

        return $this->as()->postJson('/api/v1/erp/dye-machines', [
            'machine_no' => 'MFL-01', 'machine_type_id' => $lists->firstWhere('name', 'Soft Flow')['id'], 'brand_id' => $lists->firstWhere('name', 'Fongs')['id'],
            'origin_country_id' => $country, 'capacity' => 1500, 'capacity_unit' => 'KG', 'sl_no' => '01', 'floor_id' => $floor->id, 'mc_version' => '0', 'prod_status' => 'FREE',
        ])->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
