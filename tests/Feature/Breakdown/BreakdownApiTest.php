<?php

declare(strict_types=1);

namespace Tests\Feature\Breakdown;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Breakdown\Models\RootCause;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\Technician;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The full repair chain, over the API (API 12, SRS 15) — assign, hold,
 * resume, close, cancel. `hold`/`resume`/`cancel` had no API endpoint at
 * all before this; `assign`/`close` existed but were never exercised
 * against a real technician/failure-taxonomy lookup until `formOptions()`.
 */
class BreakdownApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private Technician $technician;

    private User $engineer;

    private User $technicianUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        $this->technician = WorkOrderFixture::technician($this->delta, $this->dhaka);

        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test');
        $this->technicianUser = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
    }

    private function reported(): Breakdown
    {
        return app(ReportBreakdown::class)->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Machine stops mid-seam, motor hums but shaft does not turn',
        ], $this->engineer->id);
    }

    /**
     * Mirrors the web `BreakdownController::show()`'s own `technicians`/
     * `failureCategories`/`failureCodes`/`rootCauses` — none of it was
     * exposed anywhere in the API before this, and the assign/close/hold
     * forms cannot be built without it.
     */
    public function test_form_options_returns_the_technician_and_failure_taxonomy_lookups(): void
    {
        $response = $this->withToken($this->tokenFor($this->engineer))
            ->getJson("/api/v1/breakdowns/{$this->reported()->id}/form-options")
            ->assertOk()
            ->assertJsonStructure(['data' => ['technicians', 'failure_categories', 'failure_codes', 'root_causes', 'hold_reasons']]);

        $this->assertContains($this->technician->id, array_column($response->json('data.technicians'), 'id'));
        $this->assertContains('AWAITING_PARTS', $response->json('data.hold_reasons'));
    }

    public function test_the_full_repair_chain_from_report_to_close(): void
    {
        $breakdown = $this->reported();
        $token = $this->tokenFor($this->engineer);

        $this->withToken($token)->postJson("/api/v1/breakdowns/{$breakdown->id}/acknowledge")
            ->assertOk()->assertJsonPath('data.status', 'ACKNOWLEDGED');

        $this->withToken($token)->postJson("/api/v1/breakdowns/{$breakdown->id}/assign", [
            'technician_id' => $this->technician->id,
        ])->assertOk()
            ->assertJsonPath('data.status', 'ASSIGNED')
            ->assertJsonPath('data.assigned_technician', $this->technician->name);

        $this->withToken($token)->postJson("/api/v1/breakdowns/{$breakdown->id}/start-repair")
            ->assertOk()->assertJsonPath('data.status', 'IN_REPAIR');

        // Paused for a part to arrive — not the technician sitting idle.
        $this->withToken($token)->postJson("/api/v1/breakdowns/{$breakdown->id}/hold", [
            'reason_code' => 'AWAITING_PARTS',
            'notes' => 'Waiting on a replacement rotary hook from the store',
        ])->assertOk()->assertJsonPath('data.status', 'ON_HOLD');

        $this->withToken($token)->postJson("/api/v1/breakdowns/{$breakdown->id}/resume")
            ->assertOk()->assertJsonPath('data.status', 'IN_REPAIR');

        $this->withToken($token)->postJson("/api/v1/breakdowns/{$breakdown->id}/complete-repair")
            ->assertOk()->assertJsonPath('data.status', 'REPAIRED');

        $this->withToken($token)->postJson("/api/v1/breakdowns/{$breakdown->id}/resume-production")
            ->assertOk()->assertJsonPath('data.status', 'PRODUCTION_RESUMED');

        $this->withToken($token)->postJson("/api/v1/breakdowns/{$breakdown->id}/close", [
            'failure_code_id' => FailureCode::where('code', 'UNKNOWN')->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'NORMAL_WEAR')->firstOrFail()->id,
            'corrective_action' => 'Replaced the rotary hook',
        ])->assertOk()
            ->assertJsonPath('data.status', 'CLOSED')
            ->assertJsonPath('data.is_open', false);
    }

    public function test_a_hold_rejects_a_reason_outside_the_fixed_list(): void
    {
        $breakdown = $this->reported();
        $token = $this->tokenFor($this->engineer);

        $this->withToken($token)->postJson("/api/v1/breakdowns/{$breakdown->id}/acknowledge")->assertOk();
        $this->withToken($token)->postJson("/api/v1/breakdowns/{$breakdown->id}/start-repair")->assertOk();

        $this->withToken($token)->postJson("/api/v1/breakdowns/{$breakdown->id}/hold", [
            'reason_code' => 'BECAUSE_I_SAID_SO',
        ])->assertStatus(422)->assertJsonValidationErrors('reason_code');
    }

    /**
     * The report itself was wrong — cancelling is the other way an open
     * breakdown stops being open, not a lesser action than closing it.
     */
    public function test_a_breakdown_can_be_cancelled_while_still_open(): void
    {
        $breakdown = $this->reported();

        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/breakdowns/{$breakdown->id}/cancel", [
                'cancellation_reason' => 'Duplicate report — same stoppage as BD-DHK-202609-00001',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED')
            ->assertJsonPath('data.is_open', false);
    }

    public function test_a_technician_cannot_assign_close_or_cancel(): void
    {
        $breakdown = $this->reported();
        $token = $this->tokenFor($this->technicianUser);

        // TECHNICIAN holds acknowledge/repair but not assign/close
        // (RoleSeeder's matrix — those arrive only at $engineer).
        $this->withToken($token)->postJson("/api/v1/breakdowns/{$breakdown->id}/acknowledge")->assertOk();

        $this->withToken($token)->postJson("/api/v1/breakdowns/{$breakdown->id}/assign", [
            'technician_id' => $this->technician->id,
        ])->assertForbidden();

        $this->withToken($token)->postJson("/api/v1/breakdowns/{$breakdown->id}/cancel", [
            'cancellation_reason' => 'Not really broken',
        ])->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
