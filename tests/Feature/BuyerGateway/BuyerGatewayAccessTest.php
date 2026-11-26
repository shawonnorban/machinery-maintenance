<?php

declare(strict_types=1);

namespace Tests\Feature\BuyerGateway;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Buyer Gateway → Access & Onboarding (AnnotechERP 25/01): portal users (one
 * login per e-mail, a suspended or locked user cannot log in), permission
 * sets (one per role per buyer), onboarding (progress from the step, Live
 * completes it, the target after the start), notification preferences (one
 * per buyer per event, a muted event stays off), the shared pickers, and who
 * may.
 */
class BuyerGatewayAccessTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $hm;

    private string $zara;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Dhaka'));
        $buyer = fn (string $code, string $name, string $short) => Buyer::query()->create(['company_id' => $this->delta->id, 'code' => $code, 'name' => $name, 'short_name' => $short, 'pi_address' => 'EU', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->hm = $buyer('HM', 'H & M Hennes & Mauritz AB', 'H&M');
        $this->zara = $buyer('ITX', 'Industria de Diseño Textil S.A.', 'Zara');
    }

    public function test_portal_users(): void
    {
        $o = $this->as()->getJson('/api/v1/buyer-gateway/options')->assertOk()->json('data');
        $this->assertSame(['H&M', 'Zara'], array_column($o['buyers'], 'label'));
        $this->assertTrue($o['can_manage']);

        $this->as()->postJson('/api/v1/erp/bg-portal-users', [])->assertUnprocessable()
            ->assertJsonPath('errors.name.0', __('buyergateway.v.user_name'))->assertJsonPath('errors.email.0', __('buyergateway.v.email'))->assertJsonPath('errors.buyer_id.0', __('buyergateway.v.buyer'));
        $anna = $this->as()->postJson('/api/v1/erp/bg-portal-users', ['name' => ' Anna Lindqvist ', 'email' => 'Anna.L@HM.example', 'buyer_id' => $this->hm, 'role' => 'MERCHANDISER'])->assertCreated()->json('data');
        $this->assertSame(['Anna Lindqvist', 'anna.l@hm.example', 'H&M', 'INVITED', true], [$anna['name'], $anna['email'], $anna['buyer'], $anna['state'], $anna['login_enabled']]);
        $this->as()->postJson('/api/v1/erp/bg-portal-users', ['name' => 'Anna Two', 'email' => 'anna.l@hm.example', 'buyer_id' => $this->zara])->assertUnprocessable()->assertJsonPath('errors.email.0', __('buyergateway.v.email_taken'));
        $this->as()->postJson('/api/v1/erp/bg-portal-users', ['name' => 'X', 'email' => 'not-an-email', 'buyer_id' => $this->hm])->assertUnprocessable()->assertJsonPath('errors.email.0', __('buyergateway.v.email_format'));
        $this->as()->postJson('/api/v1/erp/bg-portal-users', ['name' => 'X', 'email' => 'x@y.example', 'buyer_id' => strtolower((string) Str::ulid())])->assertUnprocessable()->assertJsonValidationErrors(['buyer_id']);

        // Suspending switches the login off; switching it back on is refused until the user is active again.
        $this->as()->patchJson("/api/v1/erp/bg-portal-users/{$anna['id']}", ['state' => 'SUSPENDED', 'login_enabled' => true])->assertOk()->assertJsonPath('data.login_enabled', false);
        $this->as()->patchJson("/api/v1/erp/bg-portal-users/{$anna['id']}", ['login_enabled' => true])->assertUnprocessable()
            ->assertJsonPath('errors.login_enabled.0', __('buyergateway.v.login_blocked', ['name' => 'Anna Lindqvist', 'state' => __('buyergateway.state.SUSPENDED')]));
        $this->as()->patchJson("/api/v1/erp/bg-portal-users/{$anna['id']}", ['state' => 'ACTIVE', 'login_enabled' => true, 'last_seen' => '2026-11-10'])->assertOk()->assertJsonPath('data.login_enabled', true);
        $this->as()->patchJson("/api/v1/erp/bg-portal-users/{$anna['id']}", ['login_enabled' => false])->assertOk()->assertJsonPath('data.login_enabled', false);
        $rows = $this->as()->getJson('/api/v1/erp/bg-portal-users')->assertOk()->json('data');
        $this->assertSame([['Anna Lindqvist', 'ACTIVE', '2026-11-10', false]], array_map(fn ($r) => [$r['name'], $r['state'], $r['last_seen'], $r['login_enabled']], $rows));
        $this->as()->deleteJson("/api/v1/erp/bg-portal-users/{$anna['id']}")->assertNoContent();
        $this->assertSame([], $this->as()->getJson('/api/v1/erp/bg-portal-users')->json('data'));
    }

    public function test_permission_sets_onboarding_and_notifications(): void
    {
        $set = $this->as()->postJson('/api/v1/erp/bg-permission-sets', ['role' => 'MERCHANDISER', 'buyer_id' => $this->hm, 'orders' => 'FULL', 'samples' => 'FULL', 'documents' => 'DOWNLOAD', 'commercial' => 'READ_ONLY', 'may_approve' => true])->assertCreated()->json('data');
        $this->assertSame(['FULL', 'DOWNLOAD', true, '2026-11-11', 'H&M'], [$set['orders'], $set['documents'], $set['may_approve'], $set['set_on'], $set['buyer']]);
        $this->as()->postJson('/api/v1/erp/bg-permission-sets', ['role' => 'MERCHANDISER', 'buyer_id' => $this->hm])->assertUnprocessable()->assertJsonPath('errors.role.0', __('buyergateway.v.set_taken', ['buyer' => 'H&M']));
        $this->as()->postJson('/api/v1/erp/bg-permission-sets', ['role' => 'MERCHANDISER', 'buyer_id' => $this->zara])->assertCreated()->assertJsonPath('data.commercial', 'HIDDEN');
        $this->as()->postJson('/api/v1/erp/bg-permission-sets', ['role' => 'OWNER', 'buyer_id' => $this->zara])->assertUnprocessable()->assertJsonValidationErrors(['role']);
        $this->as()->patchJson("/api/v1/erp/bg-permission-sets/{$set['id']}", ['may_approve' => false])->assertOk()->assertJsonPath('data.may_approve', false);

        // Onboarding: progress from the step; Live completes it; not live is not complete.
        $this->as()->postJson('/api/v1/erp/bg-onboardings', [])->assertUnprocessable()->assertJsonPath('errors.buyer_name.0', __('buyergateway.v.buyer_name'));
        $next = $this->as()->postJson('/api/v1/erp/bg-onboardings', ['buyer_name' => 'Next Retail', 'step' => 'PERMISSIONS_SET', 'owner_id' => $this->owner->id, 'target_live' => '2026-12-15', 'users_invited' => 4])->assertCreated()->json('data');
        $this->assertSame([50, 'ON_TRACK', '2026-11-11', $this->owner->name], [$next['progress'], $next['state'], $next['started_on'], $next['owner']]);
        $this->as()->postJson('/api/v1/erp/bg-onboardings', ['buyer_name' => 'next retail'])->assertUnprocessable()->assertJsonPath('errors.buyer_name.0', __('buyergateway.v.onboarding_taken'));
        $this->as()->postJson('/api/v1/erp/bg-onboardings', ['buyer_name' => 'Matalan', 'started_on' => '2026-11-10', 'target_live' => '2026-11-01'])->assertUnprocessable()->assertJsonPath('errors.target_live.0', __('buyergateway.v.target_before_start'));
        $this->assertSame([12, 38, 88], array_map(fn ($s) => $this->as()->patchJson("/api/v1/erp/bg-onboardings/{$next['id']}", ['step' => $s])->json('data.progress'), ['CONTRACT_SIGNED', 'USERS_INVITED', 'TRAINING_DONE']));
        $this->as()->patchJson("/api/v1/erp/bg-onboardings/{$next['id']}", ['step' => 'LIVE'])->assertOk()->assertJsonPath('data.progress', 100)->assertJsonPath('data.state', 'COMPLETE');
        $this->as()->patchJson("/api/v1/erp/bg-onboardings/{$next['id']}", ['step' => 'TRAINING_DONE'])->assertOk()->assertJsonPath('data.state', 'ON_TRACK');
        $this->as()->patchJson("/api/v1/erp/bg-onboardings/{$next['id']}", ['state' => 'BLOCKED'])->assertOk()->assertJsonPath('data.state', 'BLOCKED');

        // Notifications: one per buyer per event; muted stays off.
        $pref = $this->as()->postJson('/api/v1/erp/bg-notification-prefs', ['event' => 'SAMPLE_APPROVED', 'buyer_id' => $this->hm, 'channel' => 'BOTH', 'recipients' => 'MERCHANDISERS'])->assertCreated()->json('data');
        $this->assertSame(['BOTH', 'IMMEDIATE', 'MERCHANDISERS', true], [$pref['channel'], $pref['digest'], $pref['recipients'], $pref['enabled']]);
        $this->as()->postJson('/api/v1/erp/bg-notification-prefs', ['event' => 'SAMPLE_APPROVED', 'buyer_id' => $this->hm])->assertUnprocessable()->assertJsonPath('errors.event.0', __('buyergateway.v.pref_taken', ['buyer' => 'H&M']));
        $this->as()->patchJson("/api/v1/erp/bg-notification-prefs/{$pref['id']}", ['channel' => 'MUTED'])->assertOk()->assertJsonPath('data.enabled', false);
        $this->as()->patchJson("/api/v1/erp/bg-notification-prefs/{$pref['id']}", ['enabled' => true])->assertUnprocessable()->assertJsonPath('errors.enabled.0', __('buyergateway.v.muted'));
        $this->as()->patchJson("/api/v1/erp/bg-notification-prefs/{$pref['id']}", ['channel' => 'EMAIL', 'enabled' => true])->assertOk()->assertJsonPath('data.enabled', true);
        $muted = $this->as()->postJson('/api/v1/erp/bg-notification-prefs', ['event' => 'ANNOUNCEMENT_POSTED', 'buyer_id' => $this->zara, 'channel' => 'MUTED', 'digest' => 'WEEKLY'])->assertCreated()->json('data');
        $this->assertFalse($muted['enabled']);

        // A buyer with portal records stays.
        $this->as()->postJson('/api/v1/erp/bg-portal-users', ['name' => 'Marta Ruiz', 'email' => 'm.ruiz@zara.example', 'buyer_id' => $this->zara])->assertCreated();
        $this->assertSame(1, DB::table('bg_portal_users')->where('buyer_id', $this->zara)->count());
    }

    public function test_who_may(): void
    {
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $this->as($auditor)->getJson('/api/v1/erp/bg-portal-users')->assertOk();
        $this->as($auditor)->getJson('/api/v1/buyer-gateway/options')->assertOk()->assertJsonPath('data.can_manage', false);
        $this->as($auditor)->postJson('/api/v1/erp/bg-portal-users', ['name' => 'A', 'email' => 'a@b.example', 'buyer_id' => $this->hm])->assertForbidden();
        $this->as($manager)->postJson('/api/v1/erp/bg-portal-users', ['name' => 'A', 'email' => 'a@b.example', 'buyer_id' => $this->hm])->assertCreated();
        $this->as($keeper)->getJson('/api/v1/erp/bg-onboardings')->assertForbidden();
        $this->as($keeper)->getJson('/api/v1/buyer-gateway/options')->assertForbidden();

        // Another company sees none of it and cannot use these buyers.
        $other = TenantFixture::company('Other Ltd', 'OTH');
        TenantFixture::actingAsTenant($other);
        $stranger = TenantFixture::user($other, 'COMPANY_OWNER', 'owner@other.test');
        $this->assertSame([], $this->as($stranger, $other)->getJson('/api/v1/erp/bg-portal-users')->assertOk()->json('data'));
        $this->as($stranger, $other)->postJson('/api/v1/erp/bg-portal-users', ['name' => 'A', 'email' => 'z@b.example', 'buyer_id' => $this->hm])->assertUnprocessable()->assertJsonValidationErrors(['buyer_id']);
    }

    private function as(?User $user = null, ?Company $company = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, ($company ?? $this->delta)->id, 'Test token');

        return $this->withToken($plain);
    }
}
