<?php

declare(strict_types=1);

namespace Tests\Feature\WorkStudy;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Work Study → Floor Operations (AnnotechERP 14/07): a floor's hour of
 * sewing output corrected (a line put back to the recorded figure loses its
 * adjustment); wash requisitions numbered on save and moved a step at a time
 * until complete; a line's man-minutes booked away by reason, Man OT added
 * up from the overtime hours — each sheet printed and exported.
 */
class WorkStudyFloorOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private Floor $floor;

    private PlanningLine $line;

    private PlanningLine $line2;

    private Buyer $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Building', 'code' => 'BLD-1']);
        $this->floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Sewing', 'code' => 'SEW-1']);
        $this->line = PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'code' => 'LINE01', 'name' => 'Line 01', 'std_manpower' => 36, 'std_machines' => 30, 'std_hours' => 10, 'std_efficiency' => 65, 'active' => true]);
        $this->line2 = PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'code' => 'LINE02', 'name' => 'Line 02', 'std_manpower' => 36, 'std_machines' => 30, 'std_hours' => 10, 'std_efficiency' => 65, 'active' => true]);
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true]);
    }

    public function test_sewing_output_adjustments_and_man_minutes(): void
    {
        $options = $this->as()->getJson('/api/v1/workstudy/floor-ops/options')->assertOk()->json('data');
        $this->assertSame(range(1, 11), $options['hours']);
        $this->assertCount(15, $options['reasons']);
        $this->assertSame('9th(6PM)', $options['slots'][0]['label']);

        // The hour sheet: every line, recorded beside Prod; nothing saved yet.
        $hour = 'floor=SEW-1&date=2026-10-25&hour=2';
        $sheet = $this->as()->getJson("/api/v1/workstudy/floor-ops/output?{$hour}")->assertOk()->json('data.rows');
        $this->assertSame([['LINE01', 0, 0, null], ['LINE02', 0, 0, null]], array_map(fn ($r) => [$r['line'], $r['recorded'], $r['prod'], $r['id']], $sheet));
        $this->as()->getJson('/api/v1/workstudy/floor-ops/output?floor=SEW-1&date=2026-10-25&hour=12')->assertUnprocessable()->assertJsonPath('errors.hour.0', 'Hour is required.');

        // Save: LINE01 corrected to 55 (Increased), LINE02 left at the recorded figure writes nothing.
        $sheet[0] = ['buyer_id' => $this->buyer->id, 'order_no' => 'LPP-ORD-01', 'item_name' => 'T-Shirt', 'prod' => 55] + $sheet[0];
        $saved = $this->as()->putJson("/api/v1/workstudy/floor-ops/output?{$hour}", ['rows' => $sheet])->assertOk()->json('data.rows');
        $this->assertSame([55, 0], array_column($saved, 'prod'));
        $this->assertSame(1, DB::table('ws_output_adjustments')->count());
        $record = $this->as()->getJson('/api/v1/erp/ws-output-adjustments')->json('data.0');
        $this->assertSame(['LINE01', 'LPP', 2, 0, 55, 'INCREASED'], [$record['line'], $record['buyer'], $record['hour'], $record['recorded_qty'], $record['adjusted_qty'], $record['status']]);

        // Retyping the recorded figure takes the adjustment away.
        $saved[0]['prod'] = 0;
        $this->as()->putJson("/api/v1/workstudy/floor-ops/output?{$hour}", ['rows' => $saved])->assertOk();
        $this->assertSame(0, DB::table('ws_output_adjustments')->count());

        // New production adjustment: Brickland's message without a date; a correction to the recorded figure corrects nothing; one a line an hour.
        $row = ['floor_id' => $this->floor->id, 'planning_line_id' => $this->line2->id, 'hour' => 3, 'recorded_qty' => 120, 'adjusted_qty' => 110, 'reason' => 'Bundle counted twice'];
        $this->as()->postJson('/api/v1/erp/ws-output-adjustments', $row)->assertUnprocessable()->assertJsonPath('errors.adjust_date.0', 'Date is required.');
        $this->as()->postJson('/api/v1/erp/ws-output-adjustments', ['adjust_date' => '2026-10-25', 'adjusted_qty' => 120] + $row)->assertUnprocessable()->assertJsonValidationErrors('adjusted_qty');
        $made = $this->as()->postJson('/api/v1/erp/ws-output-adjustments', ['adjust_date' => '2026-10-25'] + $row)->assertCreated()->json('data');
        $this->assertSame('DECREASED', $made['status']);
        $this->as()->postJson('/api/v1/erp/ws-output-adjustments', ['adjust_date' => '2026-10-25'] + $row)->assertUnprocessable()->assertJsonValidationErrors('planning_line_id');
        // The sheet shows it, the recorded figure as it was booked.
        $h3 = $this->as()->getJson('/api/v1/workstudy/floor-ops/output?floor=SEW-1&date=2026-10-25&hour=3')->json('data.rows');
        $this->assertSame([120, 110], [$h3[1]['recorded'], $h3[1]['prod']]);

        $pdf = $this->as()->get('/api/v1/workstudy/floor-ops/export/output/pdf?floor=SEW-1&date=2026-10-25&hour=3')->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->as()->get('/api/v1/workstudy/floor-ops/export/output/xlsx?floor=SEW-1&date=2026-10-25&hour=3')->assertOk();

        // Man minutes: fifteen reasons; Man OT is added up from the overtime hours.
        $day = 'floor=SEW-1&line=LINE01&date=2026-10-25';
        $mm = $this->as()->getJson("/api/v1/workstudy/floor-ops/minutes?{$day}")->assertOk()->json('data.rows');
        $this->assertCount(15, $mm);
        $this->assertSame('Manpower Used In Others', $mm[0]['reason']);
        $mm[0] = ['used_op' => 2, 'used_aso' => 1, 'slots' => ['9' => ['op' => 2, 'aso' => 0], '10' => ['op' => 1, 'aso' => 1]] + $mm[0]['slots'], 'ot_op' => 99] + $mm[0];
        $mm = $this->as()->putJson("/api/v1/workstudy/floor-ops/minutes?{$day}", ['rows' => $mm])->assertOk()->json('data.rows');
        $this->assertSame([2, 1, 3, 1], [$mm[0]['used_op'], $mm[0]['used_aso'], $mm[0]['ot_op'], $mm[0]['ot_aso']]);
        $this->assertSame(['9' => ['op' => 2, 'aso' => 0], '10' => ['op' => 1, 'aso' => 1]], json_decode(DB::table('ws_man_minute_adjustments')->value('ot_slots'), true));
        $this->assertSame(1, DB::table('ws_man_minute_adjustments')->count());

        // New man-minute adjustment: nobody booked away is no adjustment; one a line a day a reason.
        $new = ['adjust_date' => '2026-10-25', 'floor_id' => $this->floor->id, 'planning_line_id' => $this->line->id, 'reason' => 'Training Center', 'used_op' => 0, 'used_aso' => 0];
        $this->as()->postJson('/api/v1/erp/ws-man-minute-adjustments', $new)->assertUnprocessable()->assertJsonPath('errors.used_aso.0', 'Book at least one operator or ASO away.');
        $this->as()->postJson('/api/v1/erp/ws-man-minute-adjustments', ['used_op' => 4] + $new)->assertCreated()->assertJsonPath('data.line', 'LINE01');
        $this->as()->postJson('/api/v1/erp/ws-man-minute-adjustments', ['used_op' => 1] + $new)->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->as()->postJson('/api/v1/erp/ws-man-minute-adjustments', ['reason' => 'Late Coming', 'used_op' => 1] + $new)->assertUnprocessable()->assertJsonValidationErrors('reason');

        // Clearing a reason's boxes withdraws it.
        $mm = $this->as()->getJson("/api/v1/workstudy/floor-ops/minutes?{$day}")->json('data.rows');
        $mm[0] = ['used_op' => 0, 'used_aso' => 0, 'slots' => array_map(fn () => ['op' => 0, 'aso' => 0], $mm[0]['slots'])] + $mm[0];
        $this->as()->putJson("/api/v1/workstudy/floor-ops/minutes?{$day}", ['rows' => $mm])->assertOk();
        $this->assertSame(['Training Center'], DB::table('ws_man_minute_adjustments')->pluck('reason')->all());

        $pdf = $this->as()->get("/api/v1/workstudy/floor-ops/export/minutes/pdf?{$day}")->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_wash_requisitions_move_a_step_at_a_time(): void
    {
        $row = ['requisition_date' => '2026-10-25', 'attn_to' => $this->owner->id, 'floor_id' => $this->floor->id, 'planning_line_id' => $this->line->id, 'buyer_id' => $this->buyer->id,
            'style_no' => 'STY-01', 'order_no' => 'LPP-ORD-01', 'order_qty' => 5000, 'df_process' => 'Enzyme', 'hourly_production' => 60, 'daily_production' => 600,
            'start_date' => '2026-10-26', 'close_date' => '2026-10-28', 'status' => 'DRAFT_CREATED'];

        // Brickland's message without a wash quantity; the close date never before the start.
        $this->as()->postJson('/api/v1/erp/ws-wash-requisitions', $row)->assertUnprocessable()->assertJsonPath('errors.wash_qty.0', 'Wash Qty (Pcs) is required.');
        $this->as()->postJson('/api/v1/erp/ws-wash-requisitions', ['wash_qty' => 1200, 'close_date' => '2026-10-20'] + $row)->assertUnprocessable()->assertJsonValidationErrors('close_date');

        // Numbered on save by the month raised in.
        $first = $this->as()->postJson('/api/v1/erp/ws-wash-requisitions', ['wash_qty' => 1200, 'requisition_no' => 'MINE'] + $row)->assertCreated()->json('data');
        $second = $this->as()->postJson('/api/v1/erp/ws-wash-requisitions', ['wash_qty' => 800, 'style_no' => 'STY-02'] + $row)->assertCreated()->json('data');
        $this->assertSame(['GMT-WR-2026100001', 'GMT-WR-2026100002'], [$first['requisition_no'], $second['requisition_no']]);
        $this->assertSame(['LPP', 'LINE01', $this->owner->name], [$first['buyer'], $first['line'], $first['attn_to_name']]);

        // The list: still open by default; Take Action moves each a step.
        $list = $this->as()->getJson('/api/v1/workstudy/floor-ops/wash')->assertOk()->json('data');
        $this->assertSame([2, 2], [count($list['rows']), $list['open']]);
        foreach (['submit' => 'SUBMITTED', 'verify' => 'VERIFIED', 'complete' => 'COMPLETE'] as $action => $status) {
            $this->as()->postJson('/api/v1/workstudy/floor-ops/wash/action', ['rows' => [['id' => $first['id'], 'action' => $action]]])->assertOk();
            $this->assertSame($status, DB::table('ws_wash_requisitions')->where('id', $first['id'])->value('status'));
        }
        // A complete requisition has no action left, and leaves the open list.
        $this->as()->postJson('/api/v1/workstudy/floor-ops/wash/action', ['rows' => [['id' => $first['id'], 'action' => 'cancel']]])
            ->assertUnprocessable()->assertJsonPath('errors', ['rows.0.action' => ['GMT-WR-2026100001 is complete — it has no action left.']]);
        $this->assertSame(['GMT-WR-2026100002'], array_column($this->as()->getJson('/api/v1/workstudy/floor-ops/wash')->json('data.rows'), 'requisition_no'));
        $this->assertSame(['GMT-WR-2026100001'], array_column($this->as()->getJson('/api/v1/workstudy/floor-ops/wash?status=COMPLETE')->json('data.rows'), 'requisition_no'));
        $this->assertCount(2, $this->as()->getJson('/api/v1/workstudy/floor-ops/wash?status=ALL')->json('data.rows'));
        $this->assertSame(['GMT-WR-2026100002'], array_column($this->as()->getJson('/api/v1/workstudy/floor-ops/wash?status=ALL&style=sty-02')->json('data.rows'), 'requisition_no'));

        $pdf = $this->as()->get('/api/v1/workstudy/floor-ops/export/wash/pdf?status=ALL')->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->as()->get('/api/v1/workstudy/floor-ops/export/wash/xlsx?status=ALL')->assertOk();

        // A line chief reads, never moves.
        $chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $this->as($chief)->getJson('/api/v1/workstudy/floor-ops/wash')->assertOk();
        $this->as($chief)->postJson('/api/v1/workstudy/floor-ops/wash/action', ['rows' => [['id' => $second['id'], 'action' => 'submit']]])->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
