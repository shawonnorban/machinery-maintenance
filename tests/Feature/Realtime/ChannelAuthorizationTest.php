<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Who may subscribe to what (SRS 29, ADR-018).
 *
 * A websocket subscription bypasses every controller, policy and global scope
 * in the application: once a client is on a channel it receives whatever is
 * broadcast there for as long as it stays connected. These tests are the only
 * thing standing between that and another company's data.
 */
class ChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Company $beta;

    private Factory $dhaka;

    private Factory $gazipur;

    private Factory $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        $this->gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');

        $this->beta = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $this->theirs = TenantFixture::factory($this->beta, 'Narayanganj Unit', 'NGJ');

        TenantFixture::actingAsTenant($this->delta);

        // The real broadcaster, only here. The null one authorizes every
        // channel for everybody, so these tests would pass against it while
        // proving nothing — which is exactly the failure they exist to catch.
        // No server is contacted: authorization is computed locally.
        config(['broadcasting.default' => 'reverb']);

        // Channel callbacks are registered on the broadcaster instance, not
        // globally, so the file has to be read again for the driver we just
        // switched to. Without this every channel is unknown and therefore
        // refused — including the ones that should be allowed.
        require base_path('routes/channels.php');
    }

    private function authorize(User $user, string $channel)
    {
        return $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-'.$channel,
        ]);
    }

    public function test_a_member_may_join_their_own_company_channel(): void
    {
        $user = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->authorize($user, 'company.'.$this->delta->id)->assertOk();
    }

    public function test_a_stranger_may_not_join_another_companys_channel(): void
    {
        $user = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        // The single most important assertion in this file: an owner of one
        // company is nobody at all in another.
        $this->authorize($user, 'company.'.$this->beta->id)->assertForbidden();
    }

    public function test_a_removed_member_loses_the_channel(): void
    {
        $user = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm2@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->authorize($user, 'company.'.$this->delta->id)->assertOk();

        CompanyUser::where('user_id', $user->id)
            ->where('company_id', $this->delta->id)
            ->update(['status' => 'INACTIVE']);

        // A session cookie outlives a membership. Without the status check the
        // person would keep receiving events after being taken off the company.
        $this->authorize($user, 'company.'.$this->delta->id)->assertForbidden();
    }

    public function test_factory_reach_is_enforced_not_just_company_membership(): void
    {
        $scoped = TenantFixture::user(
            $this->delta,
            'MAINTENANCE_MANAGER',
            'dhaka-only@delta.test',
            factoryId: $this->dhaka->id,
        );

        TenantFixture::actingAsTenant($this->delta);

        $this->authorize($scoped, 'factory.'.$this->dhaka->id)->assertOk();

        // The same rule that hides Gazipur on screen has to hide it on the
        // socket, or the scoping is decoration (ADR-042).
        $this->authorize($scoped, 'factory.'.$this->gazipur->id)->assertForbidden();
    }

    public function test_a_company_wide_role_reaches_every_factory_in_that_company(): void
    {
        $user = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->authorize($user, 'factory.'.$this->dhaka->id)->assertOk();
        $this->authorize($user, 'factory.'.$this->gazipur->id)->assertOk();
        $this->authorize($user, 'factory.'.$this->theirs->id)->assertForbidden();
    }

    public function test_a_user_channel_belongs_to_one_person(): void
    {
        $mine = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $theirs = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech2@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->authorize($mine, 'user.'.$mine->id)->assertOk();

        // Colleagues in the same company, and still not each other's
        // notifications.
        $this->authorize($mine, 'user.'.$theirs->id)->assertForbidden();
    }

    public function test_a_guest_may_not_subscribe_to_anything(): void
    {
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-company.'.$this->delta->id,
        ])->assertStatus(403);
    }

    public function test_an_unknown_channel_is_refused(): void
    {
        $user = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner2@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        // Nothing is authorized by default: a channel nobody defined must not
        // become a channel everybody can join.
        $this->authorize($user, 'company.'.$this->delta->id.'.secrets')->assertForbidden();
        $this->authorize($user, 'admin')->assertForbidden();
    }
}
