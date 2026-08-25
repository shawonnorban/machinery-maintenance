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
 * Ending a customer, in two steps that are not the same step.
 *
 * Closing is reversible: Company soft-deletes, so the account stops working
 * and every row stays on disk. Erasing is not, and is reachable only from an
 * account that is already closed — nobody should be able to get from a working
 * customer to an empty database on one screen.
 *
 * The middleware half is the half that was missing for suspension, and the
 * same trap is here: a closed company resolves to null, and a null company is
 * exactly what the suspension check used to wave through.
 */
class TenantLifecycleTest extends TestCase
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

    public function test_closing_needs_the_code_typed_exactly(): void
    {
        $this->actingAs($this->staff)
            ->from($this->tenantUrl())
            ->delete($this->tenantUrl(), [
                'confirm_code' => 'dal',
                'reason' => 'Contract ended on 31 August; customer confirmed by email.',
            ])
            ->assertSessionHasErrors('confirm_code');

        // A confirm() dialog is dismissed by reflex. Nothing happens here
        // without the code read off the screen and copied.
        $this->assertNotNull(Company::withoutGlobalScope(TenantScope::class)->find($this->delta->id));
    }

    public function test_closing_needs_a_reason(): void
    {
        $this->actingAs($this->staff)
            ->from($this->tenantUrl())
            ->delete($this->tenantUrl(), ['confirm_code' => 'DAL', 'reason' => 'no'])
            ->assertSessionHasErrors('reason');

        $this->assertNotNull(Company::withoutGlobalScope(TenantScope::class)->find($this->delta->id));
    }

    public function test_a_closed_customer_cannot_reach_the_product(): void
    {
        $this->close();

        $this->signOut();

        // The trap: a soft-deleted company resolves to null, and a null company
        // used to skip the suspension check entirely and let the owner in with
        // a context pointing at nothing.
        $this->actingAs($this->owner)->get('/app/dashboard')->assertForbidden();
        $this->actingAs($this->owner)->get('/app/assets')->assertForbidden();
    }

    public function test_they_are_told_the_account_is_closed_not_suspended(): void
    {
        $this->close();

        $this->signOut();

        $this->actingAs($this->owner)
            ->get('/app/dashboard')
            ->assertForbidden()
            ->assertSee(__('tenancy.closed_title'))
            // Not the suspension screen: a suspension lifts this afternoon and
            // this account has ended, which is a different thing to be told.
            ->assertDontSee(__('tenancy.suspended_title'));
    }

    public function test_a_json_caller_gets_its_own_code(): void
    {
        $this->close();

        $this->signOut();

        $this->actingAs($this->owner)
            ->getJson('/app/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'TENANT_CLOSED');
    }

    public function test_signing_out_still_works(): void
    {
        $this->close();

        $this->signOut();

        $this->actingAs($this->owner)->post('/logout')->assertRedirect(route('login'));
    }

    public function test_closing_deletes_no_data(): void
    {
        $assets = DB::table('assets')->count();

        $this->close();

        // The whole point of closing rather than erasing. A customer closed by
        // mistake on Friday is whole again on Monday.
        $this->assertSame($assets, DB::table('assets')->count());
        $this->assertNotNull(User::find($this->owner->id));
        $this->assertNotNull(
            Company::withoutGlobalScope(TenantScope::class)->withTrashed()->find($this->delta->id),
        );
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

        $this->close();

        $this->assertNull(DB::table('sessions')->where('id', 'owner-session')->value('id'));
    }

    public function test_a_closed_customer_is_off_the_grid_but_still_listed(): void
    {
        $this->close();

        $this->actingAs($this->staff)
            ->get('/platform')
            ->assertOk()
            // Still findable, because a list that forgot them would make the
            // mistake unrecoverable in practice.
            ->assertSee(__('platform.closed_customers'))
            ->assertSee('Delta Apparels Ltd');
    }

    public function test_reopening_gives_the_customer_everything_back(): void
    {
        $this->close();

        $this->actingAs($this->staff)
            ->post('/platform/tenants/'.$this->delta->id.'/restore')
            ->assertRedirect();

        $this->signOut();

        $this->actingAs($this->owner)->get('/app/dashboard')->assertOk();
    }

    public function test_a_working_customer_cannot_be_erased(): void
    {
        // Two decisions on two days. There is no route from here to an empty
        // database without closing the account first.
        $this->actingAs($this->staff)
            ->delete($this->tenantUrl().'/erase', [
                'confirm_code' => 'DAL',
                'reason' => 'Contract ended on 31 August; customer confirmed by email.',
            ])
            ->assertNotFound();

        $this->assertNotNull(Company::withoutGlobalScope(TenantScope::class)->find($this->delta->id));
    }

    public function test_erasing_needs_the_code_too(): void
    {
        $this->close();

        $this->actingAs($this->staff)
            ->from('/platform')
            ->delete($this->tenantUrl().'/erase', [
                'confirm_code' => 'WRONG',
                'reason' => 'Customer asked for their records to be destroyed.',
            ])
            ->assertSessionHasErrors('purge_code');

        $this->assertNotNull(
            Company::withoutGlobalScope(TenantScope::class)->withTrashed()->find($this->delta->id),
        );
    }

    public function test_erasing_takes_the_data_with_it(): void
    {
        $this->close();

        $this->erase();

        $this->assertNull(
            Company::withoutGlobalScope(TenantScope::class)->withTrashed()->find($this->delta->id),
        );

        // The foreign keys cascade, which is what makes this one statement
        // rather than a list of tables somebody has to remember to extend.
        $this->assertSame(0, DB::table('assets')->where('company_id', $this->delta->id)->count());
        $this->assertSame(0, DB::table('factories')->where('company_id', $this->delta->id)->count());
        $this->assertSame(0, DB::table('company_users')->where('company_id', $this->delta->id)->count());
    }

    public function test_the_audit_row_outlives_the_customer(): void
    {
        $this->close();
        $this->erase();

        // audit_logs.company_id is nullOnDelete on purpose: what was done, by
        // whom and when survives the data it describes. A row that only
        // pointed at the company would be left saying nothing at all.
        $rows = AuditLog::withoutGlobalScope(TenantScope::class)
            ->where('entity_label', 'Delta Apparels Ltd')
            ->where('action', 'DELETED')
            ->get()
            // Not ordered by time: closing and erasing land in the same second,
            // and which of two rows sharing a timestamp comes back first is
            // whatever the database feels like.
            ->keyBy(fn (AuditLog $row): string => (string) ($row->new_values_json['reason'] ?? ''));

        // Both ends survive, which is the point: the account was closed on one
        // day and destroyed on another, and the trail still says so.
        $this->assertTrue($rows->has('TENANT_CLOSED'));
        $this->assertTrue($rows->has('TENANT_ERASED'));

        // Carrying the name and code in the row itself, because the column that
        // pointed at the company has just been nulled.
        $this->assertSame('DAL', $rows['TENANT_ERASED']->new_values_json['company_code'] ?? null);
        $this->assertNull($rows['TENANT_ERASED']->company_id);
    }

    public function test_people_are_left_alone(): void
    {
        $this->close();
        $this->erase();

        // Deleting them would break every audit row and created_by that names
        // them. With no membership left they are answered by denyNoMembership,
        // which is the right sentence for the situation.
        $this->assertNotNull(User::find($this->owner->id));

        $this->signOut();
        $this->actingAs($this->owner)->get('/app/dashboard')->assertForbidden();
    }

    private function tenantUrl(): string
    {
        return '/platform/tenants/'.$this->delta->id;
    }

    private function close(): void
    {
        $this->actingAs($this->staff)
            ->delete($this->tenantUrl(), [
                'confirm_code' => 'DAL',
                'reason' => 'Contract ended on 31 August; customer confirmed by email.',
            ])
            ->assertRedirect(route('platform.tenants'));
    }

    private function erase(): void
    {
        $this->actingAs($this->staff)
            ->delete($this->tenantUrl().'/erase', [
                'confirm_code' => 'DAL',
                'reason' => 'Customer asked for their records to be destroyed.',
            ])
            ->assertRedirect(route('platform.tenants'));
    }

    private function signOut(): void
    {
        $this->flushSession();

        $this->app['auth']->forgetGuards();
    }
}
