<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\EscalationRule;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Who gets told when nobody answers, over the API (SRS 28) — mirrors the web
 * `WorkflowConfigurationTest`'s escalation-rule scenarios exactly, since both
 * sides call the same `ManageEscalationRule` action per ADR-003.
 */
class EscalationRuleApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $owner;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
    }

    private function role(string $code): Role
    {
        return Role::whereNull('company_id')->where('name', $code)->firstOrFail();
    }

    public function test_a_rule_can_be_written(): void
    {
        $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/escalation-rules', [
                'event_type' => 'BREAKDOWN_CRITICAL',
                'severity' => 'CRITICAL',
                'delay_minutes' => 30,
                'escalation_level' => 1,
                'escalation_role_id' => $this->role('MAINTENANCE_MANAGER')->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.delay_minutes', 30)
            ->assertJsonPath('data.escalation_level', 1)
            // Never null, and defaults true: a rule that keeps escalating after
            // somebody has picked the job up teaches people to ignore alerts.
            ->assertJsonPath('data.stop_on_acknowledge', true)
            ->assertJsonPath('data.max_escalations', 3);
    }

    public function test_two_rules_cannot_cover_the_same_level_for_one_event(): void
    {
        $payload = [
            'event_type' => 'BREAKDOWN_CRITICAL',
            'delay_minutes' => 30,
            'escalation_level' => 1,
            'escalation_role_id' => $this->role('MAINTENANCE_MANAGER')->id,
        ];

        $this->withToken($this->tokenFor($this->owner))->postJson('/api/v1/escalation-rules', $payload)->assertCreated();

        $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/escalation-rules', $payload + ['delay_minutes' => 45])
            ->assertStatus(422)
            ->assertJsonValidationErrors('escalation_level');

        $this->assertSame(1, EscalationRule::where('event_type', 'BREAKDOWN_CRITICAL')->count());
    }

    public function test_a_ladder_of_levels_is_allowed(): void
    {
        foreach ([[1, 30, 'MAINTENANCE_MANAGER'], [2, 60, 'FACTORY_MANAGER'], [3, 120, 'COMPANY_OWNER']] as [$level, $delay, $role]) {
            $this->withToken($this->tokenFor($this->owner))
                ->postJson('/api/v1/escalation-rules', [
                    'event_type' => 'BREAKDOWN_CRITICAL',
                    'delay_minutes' => $delay,
                    'escalation_level' => $level,
                    'escalation_role_id' => $this->role($role)->id,
                ])
                ->assertCreated();
        }

        $this->assertSame(3, EscalationRule::where('event_type', 'BREAKDOWN_CRITICAL')->count());
    }

    public function test_a_rule_cannot_name_another_companys_factory(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $theirFactory = TenantFixture::factory($other, 'Their Unit', 'BTU');

        TenantFixture::actingAsTenant($this->delta);

        $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/escalation-rules', [
                'event_type' => 'LOW_STOCK',
                'delay_minutes' => 30,
                'escalation_level' => 1,
                'escalation_role_id' => $this->role('STORE_MANAGER')->id,
                'factory_id' => $theirFactory->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('factory_id');

        $this->assertSame(0, EscalationRule::withoutGlobalScopes()->where('factory_id', $theirFactory->id)->count());
    }

    public function test_toggle_and_delete(): void
    {
        $ruleId = $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/escalation-rules', [
                'event_type' => 'LOW_STOCK',
                'delay_minutes' => 30,
                'escalation_level' => 1,
                'escalation_role_id' => $this->role('STORE_MANAGER')->id,
            ])
            ->json('data.id');

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/escalation-rules/{$ruleId}/toggle")
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $this->withToken($this->tokenFor($this->owner))
            ->deleteJson("/api/v1/escalation-rules/{$ruleId}")
            ->assertNoContent();

        $this->assertNull(EscalationRule::find($ruleId));
    }

    public function test_the_endpoints_are_closed_to_a_role_that_does_not_configure_the_company(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/escalation-rules')
            ->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
