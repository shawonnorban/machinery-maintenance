<?php

declare(strict_types=1);

namespace Tests\Feature\ProductionTracking;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\ProductionTracking\Support\PtBook;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Production Tracking (AnnotechERP 30): line capacity → daily plan (target
 * calculated, submitted, approved by someone else) → dashboards that count
 * only approved targets; Production Master Data; TV screens opened by a link
 * that previewing never replaces.
 */
class ProductionTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $manager;

    private User $viewer;

    private string $today;

    private string $line1;

    private string $line2;

    private string $finishing;

    private string $floor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->viewer = TenantFixture::user($this->delta, 'VIEWER', 'viewer@delta.test');
        $this->today = PtBook::today()->toDateString();

        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Building', 'code' => 'BLD-1']);
        $this->floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Floor 1', 'code' => 'FLR-001'])->id;
        $department = (string) Str::ulid();
        DB::table('departments')->insert(['id' => $department, 'company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Production', 'code' => 'PRD', 'created_at' => now(), 'updated_at' => now()]);

        $this->line1 = $this->sewingLine('LINE01', 1);
        $this->line2 = $this->sewingLine('LINE02', 2);
        $this->finishing = (string) Str::ulid();
        DB::table('production_lines')->insert(['id' => $this->finishing, 'company_id' => $this->delta->id, 'name' => 'Finishing Line 1', 'code' => 'FIN1', 'department_id' => $department, 'line_type' => 'FINISHING', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_plan_is_calculated_from_capacity_and_needs_someone_else_to_approve_it(): void
    {
        $this->capacity($this->line1, 'PER_HOUR', 120, 80);

        // C: 120 pcs/h × 8 h × 80 % = 768.
        $this->api($this->owner)->postJson('/api/v1/production-tracking/plans/preview', ['plan_date' => $this->today, 'section' => 'SEWING', 'line_id' => $this->line1, 'method' => 'C', 'planned_hours' => 8])
            ->assertOk()->assertJsonPath('data.calculated', 768);

        // Overriding the calculated target needs a reason.
        $this->api($this->owner)->postJson('/api/v1/production-tracking/plans', $this->plan($this->line1, ['proposed_target' => 800]))
            ->assertUnprocessable()->assertJsonValidationErrors('override_reason');

        $id = $this->api($this->owner)->postJson('/api/v1/production-tracking/plans', $this->plan($this->line1))->assertCreated()->json('data.id');
        $plan = DB::table('pt_daily_plans')->find($id);
        $this->assertSame(['DRAFT', 768, 768], [$plan->status, (int) $plan->calculated_target, (int) $plan->proposed_target]);
        $this->assertMatchesRegularExpression('/^DPP-\d{8}-001$/', $plan->plan_no);

        $this->api($this->owner)->postJson("/api/v1/production-tracking/plans/{$id}/submit")->assertOk();
        $this->assertSame('PENDING_APPROVAL', DB::table('pt_daily_plans')->where('id', $id)->value('status'));

        // A pending target is not on the dashboard yet.
        $this->api($this->owner)->getJson('/api/v1/production-tracking/board/sewing')->assertOk()->assertJsonPath('data.totals.target', 0);

        // The one who prepared it may not approve it.
        $this->api($this->owner)->postJson("/api/v1/production-tracking/plans/{$id}/approve")->assertUnprocessable();

        $this->api($this->manager)->postJson("/api/v1/production-tracking/plans/{$id}/approve", ['approved_target' => 760, 'note' => 'One operator short'])->assertOk();
        $this->assertSame(['APPROVED', 760], [DB::table('pt_daily_plans')->where('id', $id)->value('status'), (int) DB::table('pt_daily_plans')->where('id', $id)->value('approved_target')]);
        $this->api($this->owner)->getJson('/api/v1/production-tracking/board/sewing')->assertOk()->assertJsonPath('data.totals.target', 760);

        $history = collect($this->api($this->owner)->getJson("/api/v1/production-tracking/plans/{$id}/history")->assertOk()->json('data.events'))->pluck('action')->all();
        $this->assertSame(['CREATE', 'SUBMIT', 'APPROVE'], $history);
    }

    public function test_reject_needs_a_reason_and_a_revision_replaces_the_approved_plan(): void
    {
        $this->capacity($this->line2, 'PER_DAY', 1000);
        $id = $this->api($this->owner)->postJson('/api/v1/production-tracking/plans', $this->plan($this->line2, ['method' => 'B']))->assertCreated()->json('data.id');
        $this->assertSame(1000, (int) DB::table('pt_daily_plans')->where('id', $id)->value('proposed_target'));
        $this->api($this->owner)->postJson("/api/v1/production-tracking/plans/{$id}/submit")->assertOk();

        $this->api($this->manager)->postJson("/api/v1/production-tracking/plans/{$id}/reject")->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->api($this->manager)->postJson("/api/v1/production-tracking/plans/{$id}/reject", ['note' => 'Changeover after lunch'])->assertOk();

        $this->api($this->owner)->patchJson("/api/v1/production-tracking/plans/{$id}", $this->plan($this->line2, ['method' => 'B', 'planned_hours' => 6]))->assertOk();
        $this->api($this->owner)->postJson("/api/v1/production-tracking/plans/{$id}/submit")->assertOk();
        $this->api($this->manager)->postJson("/api/v1/production-tracking/plans/{$id}/approve")->assertOk();

        $this->api($this->owner)->postJson("/api/v1/production-tracking/plans/{$id}/revise")->assertOk();
        $current = DB::table('pt_daily_plans')->where('line_id', $this->line2)->where('current', true)->get();
        $this->assertCount(1, $current);
        $this->assertSame(['DRAFT', 2], [$current[0]->status, (int) $current[0]->revision]);
        // Until the revision is approved the line has no approved target.
        $this->api($this->owner)->getJson('/api/v1/production-tracking/board/sewing')->assertOk()->assertJsonPath('data.totals.target', 0);
    }

    public function test_a_plan_is_refused_without_a_capacity_and_capacities_may_not_overlap(): void
    {
        $this->api($this->owner)->postJson('/api/v1/production-tracking/plans', $this->plan($this->line1, ['method' => 'A']))
            ->assertUnprocessable();

        $this->capacity($this->line1, 'PER_HOUR', 120, 80);
        $this->api($this->owner)->postJson('/api/v1/production-tracking/capacities', ['section' => 'SEWING', 'line_id' => $this->line1, 'capacity_type' => 'PER_DAY', 'standard_capacity' => 900, 'effective_from' => $this->today])
            ->assertUnprocessable();

        // A capacity that already includes efficiency refuses the efficiency-adjusted method.
        $this->api($this->owner)->postJson('/api/v1/production-tracking/capacities', ['section' => 'SEWING', 'line_id' => $this->line2, 'capacity_type' => 'PER_HOUR', 'standard_capacity' => 100, 'default_efficiency' => 85, 'includes_efficiency' => true, 'effective_from' => $this->today])->assertCreated();
        $this->api($this->owner)->postJson('/api/v1/production-tracking/plans', $this->plan($this->line2))->assertUnprocessable();
    }

    public function test_production_master_data_counts_a_line_target_once(): void
    {
        $this->capacity($this->line1, 'PER_HOUR', 120, 80);
        $id = $this->api($this->owner)->postJson('/api/v1/production-tracking/plans', $this->plan($this->line1))->assertCreated()->json('data.id');
        $this->api($this->owner)->postJson("/api/v1/production-tracking/plans/{$id}/submit")->assertOk();
        $this->api($this->manager)->postJson("/api/v1/production-tracking/plans/{$id}/approve")->assertOk();

        // LINE01 has an approved plan (768) — its entry's target is not added on top; LINE02 has only the entry.
        $this->entry('SEWING', $this->line1, ['target' => 760, 'production' => 642, 'reject' => 9, 'rework' => 21]);
        $this->entry('SEWING', $this->line2, ['target' => 750, 'production' => 515]);
        $this->entry('CUTTING', null, ['target' => 1500, 'production' => 1320, 'bundles_ready' => 44, 'bundles_issued' => 30]);

        $totals = $this->api($this->owner)->getJson('/api/v1/production-tracking/entries?date='.$this->today)->assertOk()->json('data.totals');
        $this->assertSame([1157, 768 + 750], [$totals['SEWING']['pcs'], $totals['SEWING']['target']]);
        $this->assertSame([1320, 1500], [$totals['CUTTING']['pcs'], $totals['CUTTING']['target']]);

        $board = $this->api($this->owner)->getJson('/api/v1/production-tracking/board/sewing')->assertOk();
        $board->assertJsonPath('data.totals.target', 768 + 750)->assertJsonPath('data.totals.actual', 1157);
        $cutting = $this->api($this->owner)->getJson('/api/v1/production-tracking/board/cutting')->assertOk()->json('data.kpi');
        $this->assertSame([1500, 1320, 180], [$cutting['planned'], $cutting['ready_pcs'], $cutting['remaining']]);

        // A viewer reads the dashboards but keys nothing in.
        $this->api($this->viewer)->getJson('/api/v1/production-tracking/board/sewing')->assertOk();
        $this->api($this->viewer)->postJson('/api/v1/production-tracking/entries', ['section' => 'CUTTING', 'entry_date' => $this->today, 'production' => 1])->assertForbidden();
    }

    public function test_a_tv_opens_by_its_link_alone_and_preview_never_replaces_the_link(): void
    {
        $id = $this->api($this->owner)->postJson('/api/v1/production-tracking/tv-screens', ['name' => 'Sewing Floor 1 — TV A', 'section' => 'SEWING', 'line_ids' => [$this->line1], 'layout' => 'AUTO', 'refresh_seconds' => 30])
            ->assertCreated()->json('data.id');
        $token = $this->api($this->owner)->postJson("/api/v1/production-tracking/tv-screens/{$id}/link")->assertOk()->json('data.token');
        $this->assertSame(40, strlen($token));
        $this->assertNotSame($token, DB::table('pt_tv_screens')->where('id', $id)->value('token_hash'));

        $this->api($this->owner)->getJson("/api/v1/production-tracking/tv-screens/{$id}/preview")->assertOk()->assertJsonPath('data.name', 'Sewing Floor 1 — TV A');

        // No sign-in: a fresh request with only the link.
        app('auth')->forgetGuards();
        $this->flushHeaders();
        $this->getJson("/api/v1/public/production-tracking/tv/{$token}")->assertOk()
            ->assertJsonPath('data.name', 'Sewing Floor 1 — TV A')->assertJsonCount(1, 'data.lines');
        $this->assertNotNull(DB::table('pt_tv_screens')->where('id', $id)->value('last_seen_at'));

        $this->api($this->owner)->deleteJson("/api/v1/production-tracking/tv-screens/{$id}/link")->assertOk();
        $this->flushHeaders();
        $this->getJson("/api/v1/public/production-tracking/tv/{$token}")->assertNotFound();
    }

    private function sewingLine(string $code, int $order): string
    {
        $id = (string) Str::ulid();
        DB::table('planning_lines')->insert(['id' => $id, 'company_id' => $this->delta->id, 'floor_id' => $this->floor, 'code' => $code, 'name' => 'Line '.$order, 'display_order' => $order, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function capacity(string $line, string $type, int $standard, ?int $efficiency = null): void
    {
        $this->api($this->owner)->postJson('/api/v1/production-tracking/capacities', array_filter([
            'section' => 'SEWING', 'line_id' => $line, 'capacity_type' => $type, 'standard_capacity' => $standard,
            'default_efficiency' => $efficiency, 'effective_from' => $this->today,
        ], fn ($v) => $v !== null))->assertCreated();
    }

    private function plan(string $line, array $over = []): array
    {
        return $over + ['plan_date' => $this->today, 'section' => 'SEWING', 'line_id' => $line, 'method' => 'C', 'planned_hours' => 8, 'buyer_name' => 'LPP Spolka Akcyjna', 'style' => 'SS27-TEE01'];
    }

    private function entry(string $section, ?string $line, array $figures): void
    {
        $this->api($this->owner)->postJson('/api/v1/production-tracking/entries', ['section' => $section, 'entry_date' => $this->today, 'line_id' => $line, 'buyer_name' => 'LPP Spolka Akcyjna', 'style' => 'SS27-TEE01'] + $figures)
            ->assertCreated();
    }

    private function api(User $user): static
    {
        $this->flushHeaders();
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
