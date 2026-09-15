<?php

declare(strict_types=1);

namespace Tests\Feature\Breakdown;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Breakdown\Actions\RaiseBreakdownWorkOrder;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\WorkOrder\Models\Technician;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The line/department hard restriction and the automatic repair work order
 * (SRS 15 "Line/Department Restriction" and "once a technician is assigned").
 *
 * Chosen deliberately over ADR-065's advisory-only stance, which still governs
 * *offering* a technician for a work order — this restriction is narrower and
 * stricter: a breakdown itself, reported and repaired only by whoever's floor
 * it happened on, with a manager/engineer exempt so somebody can always
 * reassign a breakdown at two in the morning to whoever is awake.
 */
class BreakdownScopeGuardTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Department $dyeing;

    private ProductionLine $lineA;

    private ProductionLine $lineB;

    private ReportBreakdown $report;

    private TransitionBreakdown $transition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->dyeing = Department::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->dhaka->id,
            'name' => 'Dyeing',
            'code' => 'DYE',
        ]);

        $this->lineA = ProductionLine::create([
            'company_id' => $this->delta->id,
            'department_id' => $this->dyeing->id,
            'name' => 'Dye line 1',
            'code' => 'DL1',
        ]);

        $this->lineB = ProductionLine::create([
            'company_id' => $this->delta->id,
            'department_id' => $this->dyeing->id,
            'name' => 'Dye line 2',
            'code' => 'DL2',
        ]);

        $this->report = app(ReportBreakdown::class);
        $this->transition = app(TransitionBreakdown::class);
    }

    private function assetOn(ProductionLine $line, string $code): Asset
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka, $code);

        AssetLocation::find($asset->asset_location_id)->forceFill([
            'department_id' => $line->department_id,
            'production_line_id' => $line->id,
        ])->save();

        return $asset->fresh();
    }

    private function technicianUser(ProductionLine $line, string $email = 'tech@delta.test'): User
    {
        $user = TenantFixture::user($this->delta, 'TECHNICIAN', $email, $this->dhaka->id);

        WorkOrderFixture::technician(
            $this->delta, $this->dhaka, 'Line technician', 'EMP-'.substr(md5($email), 0, 6),
            $user, null, $line->department_id, $line->id,
        );

        return $user;
    }

    public function test_a_technician_cannot_report_a_breakdown_outside_their_own_line(): void
    {
        $user = $this->technicianUser($this->lineA);
        $otherLinesAsset = $this->assetOn($this->lineB, 'DYE-B-01');

        try {
            $this->report->handle([
                'asset_id' => $otherLinesAsset->id,
                'problem_description' => 'Dye vat overheating',
            ], $user->id);
            $this->fail('Reporting outside the technician\'s own line must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(403, $e->status);
        }
    }

    public function test_a_technician_can_report_a_breakdown_on_their_own_line(): void
    {
        $user = $this->technicianUser($this->lineA);
        $ownAsset = $this->assetOn($this->lineA, 'DYE-A-01');

        $breakdown = $this->report->handle([
            'asset_id' => $ownAsset->id,
            'problem_description' => 'Dye vat overheating',
        ], $user->id);

        $this->assertSame('REPORTED', $breakdown->status);
    }

    public function test_an_engineer_is_exempt_from_the_line_restriction(): void
    {
        $engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test', $this->dhaka->id);
        $otherLinesAsset = $this->assetOn($this->lineB, 'DYE-B-02');

        // At two in the morning, someone has to be able to report or reassign
        // a breakdown to whoever is actually awake (ADR-065's own reasoning,
        // extended here to the person exempt from this narrower restriction).
        $breakdown = $this->report->handle([
            'asset_id' => $otherLinesAsset->id,
            'problem_description' => 'Dye vat overheating',
        ], $engineer->id);

        $this->assertSame('REPORTED', $breakdown->status);
    }

    public function test_a_technician_cannot_start_repair_on_a_breakdown_outside_their_own_line(): void
    {
        $engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer2@delta.test', $this->dhaka->id);
        $user = $this->technicianUser($this->lineA, 'tech2@delta.test');
        $otherLinesAsset = $this->assetOn($this->lineB, 'DYE-B-03');

        $breakdown = $this->report->handle([
            'asset_id' => $otherLinesAsset->id,
            'problem_description' => 'Dye vat overheating',
        ], $engineer->id);
        $breakdown = $this->transition->acknowledge($breakdown, $engineer->id);

        try {
            $this->transition->startRepair($breakdown, $user->id);
            $this->fail('Starting repair outside the technician\'s own line must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(403, $e->status);
        }
    }

    public function test_a_managers_cross_line_override_lets_the_assigned_technician_proceed(): void
    {
        $engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer3@delta.test', $this->dhaka->id);
        $user = $this->technicianUser($this->lineA, 'tech4@delta.test');
        $technician = Technician::forUser($user);
        $otherLinesAsset = $this->assetOn($this->lineB, 'DYE-B-04');

        $breakdown = $this->report->handle([
            'asset_id' => $otherLinesAsset->id,
            'problem_description' => 'Dye vat overheating',
        ], $engineer->id);
        $breakdown = $this->transition->acknowledge($breakdown, $engineer->id);

        // The 2am override: an engineer deliberately sends the line-A
        // technician to a line-B breakdown. Once assigned, that assignment
        // IS the authorization — the technician is not then refused their
        // own job for being outside their normal line.
        $breakdown = $this->transition->assign($breakdown, $technician->id, $engineer->id);

        $breakdown = $this->transition->startRepair($breakdown, $user->id);
        $this->assertSame('IN_REPAIR', $breakdown->status);

        $breakdown = $this->transition->completeRepair($breakdown, $user->id);
        $this->assertSame('REPAIRED', $breakdown->status);
    }

    /**
     * The chain the factory actually wants: Line Chief reports, a manager
     * acknowledges, then "Raise work order" puts the *whole* line roster
     * on the job at once — not a single manager pick — so any of them can
     * open it and start (`RaiseBreakdownWorkOrder::assignRoster()`).
     */
    public function test_raising_a_work_order_assigns_the_lines_whole_roster(): void
    {
        $engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer6@delta.test', $this->dhaka->id);
        $techA1 = $this->technicianUser($this->lineA, 'tech6@delta.test');
        $techA2 = $this->technicianUser($this->lineA, 'tech6b@delta.test');
        $techB = $this->technicianUser($this->lineB, 'tech6c@delta.test');
        $ownAsset = $this->assetOn($this->lineA, 'DYE-A-06');

        $breakdown = $this->report->handle([
            'asset_id' => $ownAsset->id,
            'problem_description' => 'Dye vat overheating',
        ], $engineer->id);

        $breakdown = $this->transition->acknowledge($breakdown, $engineer->id);
        $this->assertNull($breakdown->assigned_technician_id);

        $workOrder = app(RaiseBreakdownWorkOrder::class)->handle($breakdown, $engineer->id);

        $this->assertSame('ASSIGNED', $workOrder->status);
        $active = $workOrder->activeAssignments()->pluck('technician_id')->all();
        $this->assertContains(Technician::forUser($techA1)->id, $active);
        $this->assertContains(Technician::forUser($techA2)->id, $active);
        $this->assertNotContains(Technician::forUser($techB)->id, $active);
        // The breakdown itself carries no single name yet — that's set
        // when one of the roster actually starts the work (see the API
        // test covering that sync end to end).
        $this->assertNull($breakdown->fresh()->assigned_technician_id);
    }

    /** A factory-wide technician (no line/department of their own) is not part of any roster — the same reasoning `MaintenanceNotifier` already uses for who gets woken up. */
    public function test_raising_a_work_order_skips_factory_wide_technicians(): void
    {
        $engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer7@delta.test', $this->dhaka->id);
        $factoryWide = WorkOrderFixture::technician($this->delta, $this->dhaka, 'Floater', 'EMP-FLOAT');
        $ownAsset = $this->assetOn($this->lineA, 'DYE-A-07');

        $breakdown = $this->report->handle([
            'asset_id' => $ownAsset->id,
            'problem_description' => 'Dye vat overheating',
        ], $engineer->id);
        $breakdown = $this->transition->acknowledge($breakdown, $engineer->id);

        $workOrder = app(RaiseBreakdownWorkOrder::class)->handle($breakdown, $engineer->id);

        $this->assertSame('DRAFT', $workOrder->status);
        $this->assertSame(0, $workOrder->activeAssignments()->where('technician_id', $factoryWide->id)->count());
    }

    /**
     * A manager's explicit override still works exactly as before: once
     * `assign()` has named someone, `startRepair()` must not try to
     * self-claim on top of it (there's already an assignee).
     */
    public function test_an_explicit_assign_is_not_overridden_by_self_claim(): void
    {
        $user = $this->technicianUser($this->lineA, 'tech7@delta.test');
        $technician = Technician::forUser($user);
        $ownAsset = $this->assetOn($this->lineA, 'DYE-A-07');

        $breakdown = $this->report->handle([
            'asset_id' => $ownAsset->id,
            'problem_description' => 'Dye vat overheating',
        ], $user->id);

        $breakdown = $this->transition->acknowledge($breakdown, $user->id);
        $breakdown = $this->transition->assign($breakdown, $technician->id, $user->id);
        $assignedAt = $breakdown->assigned_at;

        $breakdown = $this->transition->startRepair($breakdown, $user->id);

        $this->assertSame($technician->id, $breakdown->assigned_technician_id);
        $this->assertTrue($assignedAt->equalTo($breakdown->assigned_at));
    }

    public function test_assigning_a_technician_automatically_raises_and_starts_the_repair_work_order(): void
    {
        $user = $this->technicianUser($this->lineA, 'tech3@delta.test');
        $technician = Technician::forUser($user);
        $ownAsset = $this->assetOn($this->lineA, 'DYE-A-04');

        $breakdown = $this->report->handle([
            'asset_id' => $ownAsset->id,
            'problem_description' => 'Dye vat overheating',
        ], $user->id);

        $breakdown = $this->transition->acknowledge($breakdown, $user->id);

        // The technician never clicks "raise work order" — assigning them is
        // what raises it, schedules it, and puts them on it.
        $breakdown = $this->transition->assign($breakdown, $technician->id, $user->id);

        $workOrder = $breakdown->activeWorkOrder();
        $this->assertNotNull($workOrder);
        $this->assertSame($breakdown->id, $workOrder->breakdown_id);
        $this->assertSame('ASSIGNED', $workOrder->status);
        $this->assertSame(1, $workOrder->activeAssignments()->where('technician_id', $technician->id)->count());

        // Starting repair on the breakdown moves the linked work order along
        // too, so its checklist/labor/parts tabs open up with it.
        $this->transition->startRepair($breakdown, $user->id);

        $this->assertSame('IN_PROGRESS', $workOrder->fresh()->status);
    }
}
