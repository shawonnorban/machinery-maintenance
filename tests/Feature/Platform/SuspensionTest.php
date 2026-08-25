<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Suspending a customer actually stops them (SRS 40).
 *
 * It did not, for a while: the status column existed, suspending wrote to it
 * and deleted the sessions, and nothing anywhere read it — so the owner signed
 * straight back in and carried on. Deleting sessions removes the people
 * already inside; only a check on the way in stops them returning.
 *
 * The second half matters as much as the first. A company that stops working
 * with no explanation reads as a fault in the product, and the customer rings
 * to ask a question the platform already knows the answer to.
 */
class SuspensionTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

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
        TenantFixture::actingAsTenant($this->delta);

        $this->staff = User::create([
            'name' => 'Platform Support',
            'email' => 'support@platform.test',
            'password' => 'correct-horse-battery',
            'status' => 'ACTIVE',
            'locale' => 'en',
            'is_platform_admin' => true,
        ]);
    }

    public function test_a_suspended_customer_cannot_reach_the_product(): void
    {
        $this->suspend('Invoice INV-2026-0042 unpaid since 30 June.');

        $this->signOut();

        // The bug this exists for: before the check, an owner signed straight
        // back in and carried on working.
        $this->actingAs($this->owner)->get('/app/dashboard')->assertForbidden();
        $this->actingAs($this->owner)->get('/app/assets')->assertForbidden();
        $this->actingAs($this->owner)->get('/app/breakdowns')->assertForbidden();
    }

    public function test_they_are_told_why_and_that_nothing_is_lost(): void
    {
        $this->suspend('Invoice INV-2026-0042 unpaid since 30 June.');

        $this->signOut();

        $this->actingAs($this->owner)
            ->get('/app/dashboard')
            ->assertForbidden()
            ->assertSee(__('tenancy.suspended_title'))
            // Verbatim, because "policy" answers nothing for somebody whose
            // factory has just lost its maintenance system.
            ->assertSee('Invoice INV-2026-0042 unpaid since 30 June.')
            // The first thing anybody in a factory fears when a system stops.
            ->assertSee(__('tenancy.suspended_data_safe'));
    }

    public function test_signing_out_still_works(): void
    {
        $this->suspend('Unpaid invoice.');

        $this->signOut();

        // Locking somebody into a page they cannot leave is a second problem
        // on top of the first.
        $this->actingAs($this->owner)->post('/logout')->assertRedirect(route('login'));
    }

    public function test_a_json_caller_gets_a_code_it_can_branch_on(): void
    {
        $this->suspend('Unpaid invoice.');

        $this->signOut();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/health')
            ->assertOk();

        $this->actingAs($this->owner)
            ->getJson('/app/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'TENANT_SUSPENDED');
    }

    public function test_everybody_already_inside_is_signed_out(): void
    {
        DB::table('sessions')->insert([
            'id' => 'owner-session',
            'user_id' => $this->owner->id,
            'ip_address' => '10.0.0.4',
            'user_agent' => 'Firefox',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->suspend('Unpaid invoice.');

        $this->assertNull(DB::table('sessions')->where('id', 'owner-session')->value('id'));
    }

    public function test_a_reason_is_required(): void
    {
        $this->actingAs($this->staff)
            ->from('/platform/tenants/'.$this->delta->id)
            ->post('/platform/tenants/'.$this->delta->id.'/suspend', ['reason' => 'no'])
            ->assertSessionHasErrors('reason');

        // Nothing happens without one. The customer is shown this sentence, so
        // there has to be a sentence.
        $this->assertFalse($this->delta->fresh()->isSuspended());
    }

    public function test_nothing_is_deleted(): void
    {
        $before = DB::table('assets')->count();

        $this->suspend('Unpaid invoice.');

        // SRS 40: cancellation does not delete data. A customer who settles on
        // Friday finds everything where they left it on Monday.
        $this->assertSame($before, DB::table('assets')->count());
        $this->assertNotNull(User::find($this->owner->id));
    }

    public function test_reactivating_restores_access_and_clears_the_reason(): void
    {
        $this->suspend('Unpaid invoice.');

        $this->actingAs($this->staff)
            ->post('/platform/tenants/'.$this->delta->id.'/suspend')
            ->assertRedirect();

        $company = $this->delta->fresh();

        $this->assertFalse($company->isSuspended());
        // A stale reason on a running company would be read as a current one.
        $this->assertNull($company->suspension_reason);
        $this->assertNull($company->suspended_at);

        $this->signOut();

        $this->actingAs($this->owner)->get('/app/dashboard')->assertOk();
    }

    public function test_both_ends_are_audited(): void
    {
        $this->suspend('Unpaid invoice.');
        $this->actingAs($this->staff)->post('/platform/tenants/'.$this->delta->id.'/suspend');

        foreach (['TENANT_SUSPENDED', 'TENANT_REACTIVATED'] as $label) {
            $this->assertSame(1, AuditLog::withoutGlobalScope(TenantScope::class)
                ->where('entity_label', $label)
                ->count(), $label);
        }
    }

    public function test_platform_staff_are_unaffected_by_a_suspension(): void
    {
        $this->suspend('Unpaid invoice.');

        // Platform staff belong to no company, so no tenant is resolved for
        // them and there is nothing to suspend. Losing the platform area
        // because a customer was stopped would make the stop irreversible.
        $this->actingAs($this->staff)->get('/platform')->assertOk();
    }

    private function suspend(string $reason): void
    {
        $this->actingAs($this->staff)
            ->post('/platform/tenants/'.$this->delta->id.'/suspend', ['reason' => $reason])
            ->assertRedirect();
    }

    private function signOut(): void
    {
        $this->flushSession();

        $this->app['auth']->forgetGuards();
    }
}
