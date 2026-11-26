<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\PasswordChange;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Department;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Access Control → User Account Registration and Password Change
 * (AnnotechERP 02/01, step B): Brickland's account fields, the User ID as a
 * second sign-in name, the account states, the forced change, the memorable
 * word on resets, and a record of every password change.
 */
class UserAccountRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private int $storekeeperRole;

    private string $factoryId;

    protected function setUp(): void
    {
        parent::setUp();

        // The breached-password check would otherwise call out to the internet.
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factoryId = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK')->id;
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->storekeeperRole = Role::where('name', 'STOREKEEPER')->whereNull('company_id')->value('id');
    }

    public function test_an_account_is_registered_with_a_chosen_password_and_signs_in_by_user_id(): void
    {
        $this->api()->postJson('/api/v1/users', $this->account([
            'username' => 'Store.Karim', 'password' => 'Karim-Store-2026', 'password_confirmation' => 'Karim-Store-2026',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.username', 'store.karim')
            ->assertJsonPath('data.user_type', 'INTERNAL')
            ->assertJsonPath('data.employee_code', 'EMP-1042')
            ->assertJsonPath('data.password', null);

        $this->postJson('/api/v1/auth/login', ['email' => 'STORE.KARIM', 'password' => 'Karim-Store-2026'])->assertCreated();
        $this->postJson('/api/v1/auth/login', ['email' => 'karim@delta.test', 'password' => 'Karim-Store-2026'])->assertCreated();

        $this->assertSame(1, PasswordChange::where('change_type', 'CREATED')->count());
    }

    public function test_locked_separated_and_expired_accounts_cannot_sign_in(): void
    {
        $this->api()->postJson('/api/v1/users', $this->account(['password' => 'Karim-Store-2026', 'password_confirmation' => 'Karim-Store-2026']))->assertCreated();
        $karim = User::where('email', 'karim@delta.test')->firstOrFail();

        foreach (['LOCKED', 'SEPARATED', 'DORMANT'] as $status) {
            $karim->forceFill(['status' => $status])->save();
            $this->postJson('/api/v1/auth/login', ['email' => 'karim@delta.test', 'password' => 'Karim-Store-2026'])->assertUnprocessable();
        }

        $karim->forceFill(['status' => 'ACTIVE', 'account_expires_at' => now()->subDay()])->save();
        $this->postJson('/api/v1/auth/login', ['email' => 'karim@delta.test', 'password' => 'Karim-Store-2026'])->assertUnprocessable();

        $karim->forceFill(['account_expires_at' => now()->addMonth()])->save();
        $this->postJson('/api/v1/auth/login', ['email' => 'karim@delta.test', 'password' => 'Karim-Store-2026'])->assertCreated();
    }

    public function test_a_read_only_account_may_look_but_not_change_anything(): void
    {
        $this->api()->postJson('/api/v1/users', $this->account(['account_status' => 'READ_ONLY']))->assertCreated();
        $karim = User::where('email', 'karim@delta.test')->firstOrFail();
        $token = $this->tokenFor($karim);

        $this->withToken($token)->getJson('/api/v1/purchase-requisitions')->assertOk();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertJsonPath('data.user.read_only', true);
        $this->withToken($token)->postJson('/api/v1/purchase-requisitions', [])->assertForbidden();
    }

    public function test_a_reset_needs_the_memorable_word_and_forces_a_change(): void
    {
        $id = $this->api()->postJson('/api/v1/users', $this->account([
            'memorable_word' => 'jamdani', 'memorable_word_confirmation' => 'jamdani',
        ]))->assertCreated()->assertJsonPath('data.has_memorable_word', true)->json('data.id');

        $this->api()->postJson('/api/v1/users/'.$id.'/reset-password', [])->assertUnprocessable()->assertJsonValidationErrors('memorable_word');
        $this->api()->postJson('/api/v1/users/'.$id.'/reset-password', ['memorable_word' => 'muslin'])->assertUnprocessable();

        $issued = $this->api()->postJson('/api/v1/users/'.$id.'/reset-password', ['memorable_word' => 'jamdani'])->assertOk()->json('data.password');
        $karim = User::findOrFail($id);
        $this->assertTrue($karim->must_change_password);

        $token = $this->tokenFor($karim);
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertJsonPath('data.user.must_change_password', true);

        $this->withToken($token)->postJson('/api/v1/auth/password', [
            'current_password' => $issued, 'password' => 'Brand-New-Pass-77', 'password_confirmation' => 'Brand-New-Pass-77',
        ])->assertSuccessful();
        $this->assertFalse($karim->fresh()->must_change_password);

        $types = PasswordChange::where('user_id', $id)->orderBy('changed_at')->pluck('change_type')->all();
        $this->assertSame(['CREATED', 'ADMIN_RESET', 'SELF'], $types);
    }

    public function test_an_administrator_sets_a_password_and_the_history_lists_it(): void
    {
        $id = $this->api()->postJson('/api/v1/users', $this->account())->assertCreated()->json('data.id');

        $this->api()->postJson('/api/v1/users/'.$id.'/password', [
            'password' => 'Typed-By-Admin-9', 'password_confirmation' => 'Typed-By-Admin-9', 'force_change' => true,
        ])->assertOk()->assertJsonPath('data.must_change_password', true);

        $this->api()->getJson('/api/v1/access/password-changes')
            ->assertOk()
            ->assertJsonFragment(['change_type' => 'ADMIN_SET', 'forced_change_next_login' => true, 'changed_by' => $this->owner->name]);
    }

    public function test_an_account_that_cannot_change_its_password_is_refused(): void
    {
        $this->api()->postJson('/api/v1/users', $this->account([
            'password' => 'Kiosk-Shared-2026', 'password_confirmation' => 'Kiosk-Shared-2026', 'cannot_change_password' => true,
        ]))->assertCreated();
        $karim = User::where('email', 'karim@delta.test')->firstOrFail();

        $this->withToken($this->tokenFor($karim))->postJson('/api/v1/auth/password', [
            'current_password' => 'Kiosk-Shared-2026', 'password' => 'Something-Else-1', 'password_confirmation' => 'Something-Else-1',
        ])->assertForbidden();
    }

    public function test_a_line_chiefs_line_is_set_and_read_back_on_the_account_list(): void
    {
        $department = Department::create(['company_id' => $this->delta->id, 'factory_id' => $this->factoryId, 'name' => 'Sewing', 'code' => 'SEW']);
        $line = ProductionLine::create(['company_id' => $this->delta->id, 'department_id' => $department->id, 'name' => 'Line 3', 'code' => 'L3']);

        $created = $this->api()->postJson('/api/v1/users', $this->account([
            'department_id' => $department->id, 'production_line_id' => $line->id, 'phone' => '01710000042',
        ]))->assertCreated();

        // User Account Registration's Edit dialog starts from the list row, so the line must be on it.
        $row = collect($this->api()->getJson('/api/v1/users?per_page=100')->assertOk()->json('data'))
            ->firstWhere('id', $created->json('data.id'));
        $this->assertSame($line->id, $row['production_line_id']);
        $this->assertSame('01710000042', $row['phone']);
    }

    /**
     * @return array<string, mixed>
     */
    private function account(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Karim Mia', 'email' => 'karim@delta.test', 'roles' => [$this->storekeeperRole], 'factory_id' => $this->factoryId,
            'user_type' => 'INTERNAL', 'employee_code' => 'EMP-1042', 'two_factor_method' => 'DISABLED',
            'justification' => 'Joins the central store.',
        ];
    }

    private function api(): static
    {
        return $this->withToken($this->tokenFor($this->owner));
    }

    private function tokenFor(User $user): string
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token');

        return $plain;
    }
}
