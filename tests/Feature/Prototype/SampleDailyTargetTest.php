<?php

declare(strict_types=1);

namespace Tests\Feature\Prototype;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleRequisition;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Prototype Desk → Sample Room Daily Target (AnnotechERP 07/03): one day
 * sheet per date, its hands present no more than the total and at least one
 * line; the figures come from the lines, a sheet past capacity saves with a
 * warning and a closed one is locked; a weekly or monthly plan spans its
 * period exactly and reads the day sheets inside it.
 */
class SampleDailyTargetTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    /** @var list<string> */
    private array $lines = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Building', 'code' => 'BLD-1']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Floor 1', 'code' => 'FLR-001']);
        foreach (['LN-001' => 'Sample Line 1', 'LN-002' => 'Sample Line 2'] as $code => $name) {
            $this->lines[] = PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $floor->id, 'code' => $code, 'name' => $name])->id;
        }
    }

    public function test_a_day_sheet_is_one_per_date_with_its_figures_and_a_closed_one_is_locked(): void
    {
        $sample = $this->sample();
        $options = $this->as()->getJson('/api/v1/prototype/day-sheets/options')->assertOk()->json('data');
        $this->assertSame(['LN-001 — Sample Line 1', 'LN-002 — Sample Line 2'], collect($options['lines'])->pluck('label')->all());
        $this->assertSame([$sample->sample_no], collect($options['samples'])->pluck('sample_no')->all());

        $head = ['target_date' => '2026-10-22', 'sample_room' => 'Sample Room 1', 'shift' => 'DAY', 'unit_company_id' => $this->delta->id, 'total_manpower' => 10, 'shift_hours' => 8];
        $this->as()->postJson('/api/v1/prototype/day-sheets', [])->assertUnprocessable()->assertJsonPath('errors.target_date.0', 'Target Date is required.');
        $this->as()->postJson('/api/v1/prototype/day-sheets', $head + ['available_manpower' => 12])->assertUnprocessable()->assertJsonPath('errors.available_manpower.0', 'Available Manpower cannot be more than Total Manpower.');
        $this->as()->postJson('/api/v1/prototype/day-sheets', $head + ['available_manpower' => 8])->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A day sheet with no lines posts nothing, so at least one is required before this can be saved.');
        $this->as()->postJson('/api/v1/prototype/day-sheets', $head + ['available_manpower' => 8, 'lines' => [['target' => 5]]])->assertUnprocessable()
            ->assertJsonPath('errors', ['lines.0.planning_line_id' => ['Pick the Line on every row of the grid.']]);
        $this->as()->postJson('/api/v1/prototype/day-sheets', $head + ['available_manpower' => 8, 'lines' => [['planning_line_id' => $this->lines[0], 'start_time' => '10:00', 'end_time' => '09:00']]])
            ->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Row 1: End Time must be after Start Time.');

        // Capacity 8 × 8 = 64; the lines' figures; the sample fills its type and order.
        $lines = [
            ['planning_line_id' => $this->lines[0], 'sample_id' => $sample->id, 'target' => 50, 'wh' => 6, 'manpower' => 4, 'target_hrs' => 24, 'actual' => 40, 'actual_hrs' => 24, 'start_time' => '08:00', 'end_time' => '14:00', 'priority' => 'HIGH'],
            ['planning_line_id' => $this->lines[1], 'target' => 40, 'wh' => 10, 'manpower' => 6, 'target_hrs' => 30],
        ];
        $sheet = $this->as()->postJson('/api/v1/prototype/day-sheets', $head + ['available_manpower' => 8, 'lines' => $lines])->assertCreated()->json('data');
        $this->assertEquals(['DRAFT', 2, 90, 40, 44.4, 8.0, 54.0, 64.0, false, null], [$sheet['state'], $sheet['lines_count'], $sheet['total_target'], $sheet['total_actual'], $sheet['achievement'], $sheet['average_wh'], $sheet['planned_hrs'], $sheet['capacity_hrs'], $sheet['over_capacity'], $sheet['warning']]);
        // Efficiency: 24 h × 40/50 earned over the 24 h it took.
        $this->assertEquals(80, $sheet['efficiency']);
        $req = SampleRequisition::query()->firstOrFail();
        $this->assertSame([$req->sample_type_id, '08:00', '14:00'], [$sheet['lines'][0]['sample_type_id'], $sheet['lines'][0]['start_time'], $sheet['lines'][0]['end_time']]);
        $this->assertSame('Delta Apparels Ltd', $sheet['company']);

        // One per date; the sheet is addressed by its date.
        $this->as()->postJson('/api/v1/prototype/day-sheets', $head + ['available_manpower' => 8, 'lines' => $lines])->assertUnprocessable()
            ->assertJsonPath('errors.target_date.0', '22 Oct 2026 already has a day sheet — open it from Existing day sheet.');
        $this->as()->getJson('/api/v1/prototype/day-sheets/2026-10-22')->assertOk()->assertJsonCount(2, 'data.lines');

        // Saving replaces the lines; planned past capacity saves with the warning.
        $over = $this->as()->putJson('/api/v1/prototype/day-sheets/2026-10-22', $head + ['available_manpower' => 8, 'lines' => [['planning_line_id' => $this->lines[0], 'target' => 100, 'target_hrs' => 70]]])->assertOk()->json('data');
        $this->assertSame([1, true, 'Saved, but planned hours (70) are past the capacity (64).'], [$over['lines_count'], $over['over_capacity'], $over['warning']]);
        $this->as()->putJson('/api/v1/prototype/day-sheets/2026-10-22', $head + ['available_manpower' => 8, 'state' => 'CLOSED', 'lines' => $lines])->assertOk()->assertJsonPath('data.locked', true);
        $this->as()->putJson('/api/v1/prototype/day-sheets/2026-10-22', $head + ['available_manpower' => 8, 'lines' => $lines])->assertUnprocessable()->assertJsonPath('errors.state.0', 'A closed day sheet can no longer be edited.');
        $this->as()->deleteJson('/api/v1/prototype/day-sheets/2026-10-22')->assertUnprocessable()->assertJsonPath('errors.state.0', 'A closed day sheet cannot be removed.');

        // A past draft is overdue; a draft is removed.
        $this->as()->postJson('/api/v1/prototype/day-sheets', array_merge($head, ['target_date' => '2026-10-01', 'available_manpower' => 5, 'lines' => [['planning_line_id' => $this->lines[0], 'target' => 10]]]))->assertCreated();
        $rows = collect($this->as()->getJson('/api/v1/prototype/day-sheets')->assertOk()->json('data'));
        $this->assertSame(['2026-10-22', '2026-10-01'], $rows->pluck('target_date')->all());
        $this->assertSame([false, true], $rows->pluck('overdue')->all());
        $this->as()->deleteJson('/api/v1/prototype/day-sheets/2026-10-01')->assertOk();
        $this->as()->getJson('/api/v1/prototype/day-sheets/2026-10-01')->assertNotFound();

        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->as($technician)->getJson('/api/v1/prototype/day-sheets')->assertForbidden();
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->getJson('/api/v1/prototype/day-sheets')->assertOk();
        $this->as($manager)->postJson('/api/v1/prototype/day-sheets', $head)->assertForbidden();
    }

    public function test_a_plan_spans_its_period_exactly_and_reads_the_day_sheets_inside_it(): void
    {
        $line = fn (int $target, int $actual) => [['planning_line_id' => $this->lines[0], 'target' => $target, 'actual' => $actual, 'target_hrs' => 8]];
        foreach (['2026-10-19' => 'Sample Room 1', '2026-10-21' => 'Sample Room 2', '2026-10-26' => 'Sample Room 1'] as $date => $room) {
            $this->as()->postJson('/api/v1/prototype/day-sheets', ['target_date' => $date, 'sample_room' => $room, 'total_manpower' => 5, 'available_manpower' => 5, 'lines' => $line(50, 30)])->assertCreated();
        }

        $form = ['period' => 'WEEKLY', 'period_start' => '2026-10-19', 'period_end' => '2026-10-24', 'target_qty' => 200, 'available_hours' => 40, 'planned_hours' => 36];
        $this->as()->postJson('/api/v1/prototype/target-plans', [])->assertUnprocessable()->assertJsonPath('errors.period.0', 'Period is required.');
        $this->as()->postJson('/api/v1/prototype/target-plans', $form)->assertUnprocessable()->assertJsonPath('errors.period_end.0', 'A weekly plan spans exactly seven days.');
        $plan = $this->as()->postJson('/api/v1/prototype/target-plans', array_merge($form, ['period_end' => '2026-10-25']))->assertCreated()->json('data');
        // Both rooms' sheets in the week count; the 26th is outside it.
        $this->assertEquals(['TP-2026-00001', 60, 30, 2, null], [$plan['plan_no'], $plan['actual'], $plan['achievement'], count($plan['day_sheets']), $plan['warning']]);

        // Naming a room narrows it to that room's sheets; planned past available saves with a warning.
        $narrow = $this->as()->putJson('/api/v1/prototype/target-plans/TP-2026-00001', array_merge($form, ['period_end' => '2026-10-25', 'sample_room' => 'sample room 1', 'planned_hours' => 45]))->assertOk()->json('data');
        $this->assertSame([30, ['2026-10-19'], 'Saved, but planned hours (45) are past the available hours (40).'], [$narrow['actual'], array_column($narrow['day_sheets'], 'target_date'), $narrow['warning']]);

        $this->as()->postJson('/api/v1/prototype/target-plans', ['period' => 'MONTHLY', 'period_start' => '2026-10-05', 'period_end' => '2026-11-04'])->assertUnprocessable()
            ->assertJsonPath('errors.period_end.0', 'A monthly plan runs from the first to the last day of one calendar month.');
        $month = $this->as()->postJson('/api/v1/prototype/target-plans', ['period' => 'MONTHLY', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'target_qty' => 300])->assertCreated()->json('data');
        $this->assertSame([90, 3], [$month['actual'], $month['day_sheets_count']]);
        $this->as()->postJson('/api/v1/prototype/target-plans', ['period' => 'DAILY', 'period_start' => '2026-10-19', 'period_end' => '2026-10-20'])->assertUnprocessable()
            ->assertJsonPath('errors.period_end.0', 'A daily plan starts and ends on the same day.');
        // Latest period first: the week of the 19th, then the month from the 1st.
        $this->assertSame(['TP-2026-00001', 'TP-2026-00002'], collect($this->as()->getJson('/api/v1/prototype/target-plans')->assertOk()->json('data'))->pluck('plan_no')->all());
    }

    /** A sample through its requisition's three approval levels. */
    private function sample(): Sample
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '616'], ['name' => 'Poland', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => 'SS27-TEE01', 'description' => 'Summer Set', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $type = collect($this->as()->getJson('/api/v1/prototype/requisitions/options')->json('data.sample_types'))->firstWhere('label', 'Fit Sample (FIT)');
        $this->as()->postJson('/api/v1/prototype/requisitions', ['req_date' => '2026-10-10', 'buyer_id' => $buyer, 'style_id' => $style, 'sample_type_id' => $type['value'], 'qty' => 1, 'required_date' => '2026-10-30'])->assertCreated();
        $this->as()->postJson('/api/v1/prototype/requisitions/SR-2026-00001/submit')->assertOk();
        foreach (range(1, 3) as $i) {
            $this->as()->postJson('/api/v1/prototype/requisitions/SR-2026-00001/decide', ['decision' => 'APPROVE'])->assertOk();
        }

        return Sample::query()->firstOrFail();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
