<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
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
 * The rules behind approvals and escalations (SRS 14, 28).
 *
 * Both engines have worked since they were built, against rules nobody could
 * write — so every company got whatever the seed said, and a factory whose
 * owner wanted to sign for anything over five lakh had no way to say so.
 */
class WorkflowConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    private function role(string $code): Role
    {
        return Role::whereNull('company_id')->where('code', $code)->firstOrFail();
    }

    private function workflow(): ApprovalWorkflow
    {
        $this->actingAs($this->owner)->post('/app/settings/approval-workflows', [
            'name' => 'Work order approvals',
            'entity_type' => 'WORK_ORDER',
        ]);

        return ApprovalWorkflow::where('name', 'Work order approvals')->firstOrFail();
    }

    public function test_a_company_can_write_its_own_chain(): void
    {
        $workflow = $this->workflow();

        $this->actingAs($this->owner)->post('/app/settings/approval-workflows/'.$workflow->id.'/rules', [
            'name' => 'Factory manager signs above 50,000',
            'role_id' => $this->role('FACTORY_MANAGER')->id,
            'min_cost' => '50000',
        ]);

        $this->actingAs($this->owner)->post('/app/settings/approval-workflows/'.$workflow->id.'/rules', [
            'name' => 'Owner signs above 500,000',
            'role_id' => $this->role('COMPANY_OWNER')->id,
            'min_cost' => '500000',
        ]);

        $rules = ApprovalRule::where('workflow_id', $workflow->id)->orderBy('sequence')->get();

        // The order is the chain: manager first, then owner.
        $this->assertCount(2, $rules);
        $this->assertSame(1, $rules[0]->sequence);
        $this->assertSame(2, $rules[1]->sequence);
        $this->assertSame(['min_cost' => '50000.0000'], $rules[0]->condition_json);
    }

    /**
     * A step with no condition matches everything.
     */
    public function test_a_step_without_a_condition_is_refused(): void
    {
        $workflow = $this->workflow();

        $this->actingAs($this->owner)
            ->from('/app/settings/approval-workflows')
            ->post('/app/settings/approval-workflows/'.$workflow->id.'/rules', [
                'name' => 'Everything',
                'role_id' => $this->role('COMPANY_OWNER')->id,
            ])
            ->assertSessionHasErrors('min_cost');

        // Otherwise a needle change would go to the company owner, and the
        // owner would learn to approve without reading.
        $this->assertSame(0, ApprovalRule::where('workflow_id', $workflow->id)->count());
    }

    public function test_removing_a_step_closes_the_gap_in_the_chain(): void
    {
        $workflow = $this->workflow();

        foreach ([['A', '10000'], ['B', '50000'], ['C', '100000']] as [$name, $cost]) {
            $this->actingAs($this->owner)->post('/app/settings/approval-workflows/'.$workflow->id.'/rules', [
                'name' => $name,
                'role_id' => $this->role('FACTORY_MANAGER')->id,
                'min_cost' => $cost,
            ]);
        }

        $middle = ApprovalRule::where('workflow_id', $workflow->id)->where('name', 'B')->firstOrFail();

        $this->actingAs($this->owner)
            ->delete('/app/settings/approval-workflows/'.$workflow->id.'/rules/'.$middle->id)
            ->assertRedirect();

        // The numbers are the order signatures are collected in, not labels.
        $remaining = ApprovalRule::where('workflow_id', $workflow->id)->orderBy('sequence')->get();

        $this->assertSame([1, 2], $remaining->pluck('sequence')->all());
        $this->assertSame(['A', 'C'], $remaining->pluck('name')->all());
    }

    public function test_a_rule_from_another_workflow_cannot_be_removed_through_this_one(): void
    {
        $workflow = $this->workflow();

        $this->actingAs($this->owner)->post('/app/settings/approval-workflows', [
            'name' => 'Transfer approvals',
            'entity_type' => 'INVENTORY_TRANSFER',
        ]);

        $other = ApprovalWorkflow::where('name', 'Transfer approvals')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/settings/approval-workflows/'.$other->id.'/rules', [
            'name' => 'Store manager',
            'role_id' => $this->role('STORE_MANAGER')->id,
            'min_cost' => '1000',
        ]);

        $rule = ApprovalRule::where('workflow_id', $other->id)->firstOrFail();

        $this->actingAs($this->owner)
            ->delete('/app/settings/approval-workflows/'.$workflow->id.'/rules/'.$rule->id)
            ->assertNotFound();

        $this->assertNotNull(ApprovalRule::find($rule->id));
    }

    public function test_escalation_rules_can_be_written(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/settings/escalations', [
                'event_type' => 'BREAKDOWN_CRITICAL',
                'severity' => 'CRITICAL',
                'delay_minutes' => 30,
                'escalation_level' => 1,
                'escalation_role_id' => $this->role('MAINTENANCE_MANAGER')->id,
            ])
            ->assertRedirect();

        $rule = EscalationRule::where('event_type', 'BREAKDOWN_CRITICAL')->firstOrFail();

        $this->assertSame(30, $rule->delay_minutes);
        $this->assertSame(1, $rule->escalation_level);
        // Almost always true: a rule that keeps escalating after somebody has
        // picked the job up teaches people to ignore the alerts.
        $this->assertTrue((bool) $rule->stop_on_acknowledge);
    }

    public function test_two_rules_cannot_cover_the_same_level_for_one_event(): void
    {
        $payload = [
            'event_type' => 'BREAKDOWN_CRITICAL',
            'delay_minutes' => 30,
            'escalation_level' => 1,
            'escalation_role_id' => $this->role('MAINTENANCE_MANAGER')->id,
        ];

        $this->actingAs($this->owner)->post('/app/settings/escalations', $payload);

        $this->actingAs($this->owner)
            ->from('/app/settings/escalations')
            ->post('/app/settings/escalations', $payload + ['delay_minutes' => 45])
            ->assertSessionHasErrors('escalation_level');

        // Two at one level would tell the same person twice about one silence.
        $this->assertSame(1, EscalationRule::where('event_type', 'BREAKDOWN_CRITICAL')->count());
    }

    public function test_a_ladder_of_levels_is_allowed(): void
    {
        foreach ([[1, 30, 'MAINTENANCE_MANAGER'], [2, 60, 'FACTORY_MANAGER'], [3, 120, 'COMPANY_OWNER']] as [$level, $delay, $role]) {
            $this->actingAs($this->owner)->post('/app/settings/escalations', [
                'event_type' => 'BREAKDOWN_CRITICAL',
                'delay_minutes' => $delay,
                'escalation_level' => $level,
                'escalation_role_id' => $this->role($role)->id,
            ]);
        }

        // Each level reaches further up than the last, which is the point.
        $this->assertSame(3, EscalationRule::where('event_type', 'BREAKDOWN_CRITICAL')->count());
    }

    public function test_a_rule_cannot_name_another_companys_factory(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $theirFactory = TenantFixture::factory($other, 'Their Unit', 'BTU');

        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($this->owner)
            ->from('/app/settings/escalations')
            ->post('/app/settings/escalations', [
                'event_type' => 'LOW_STOCK',
                'delay_minutes' => 30,
                'escalation_level' => 1,
                'escalation_role_id' => $this->role('STORE_MANAGER')->id,
                'factory_id' => $theirFactory->id,
            ])
            ->assertSessionHasErrors('factory_id');

        $this->assertSame(0, EscalationRule::withoutGlobalScopes()
            ->where('factory_id', $theirFactory->id)->count());
    }

    public function test_both_screens_are_closed_to_roles_that_do_not_configure(): void
    {
        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        // Deciding who must sign is a company decision, not a maintenance one.
        $this->actingAs($manager)->get('/app/settings/approval-workflows')->assertForbidden();
        $this->actingAs($manager)->get('/app/settings/escalations')->assertForbidden();
    }

    public function test_the_screens_render(): void
    {
        $workflow = $this->workflow();

        $this->actingAs($this->owner)->post('/app/settings/approval-workflows/'.$workflow->id.'/rules', [
            'name' => 'Factory manager signs above 50,000',
            'role_id' => $this->role('FACTORY_MANAGER')->id,
            'min_cost' => '50000',
        ]);

        $this->actingAs($this->owner)
            ->get('/app/settings/approval-workflows')
            ->assertOk()
            ->assertSee('Factory manager signs above 50,000');

        $this->actingAs($this->owner)
            ->get('/app/settings/escalations')
            ->assertOk()
            ->assertSee(__('notification.escalations'));
    }
}
