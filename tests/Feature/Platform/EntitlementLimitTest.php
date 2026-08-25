<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Identity\Actions\ManageCompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Actions\SaveFactory;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The plan limits actually limit something.
 *
 * They did not, for the whole life of the billing module: included_factories
 * could say 3 while the customer ran 30, because the only thing that ever read
 * the column was the usage bar on the billing screen. UsageMeter::wouldExceed()
 * was written, tested and never called from anywhere.
 *
 * Only BLOCK stops anything, and that distinction is the point rather than an
 * omission — a mill that commissions its 413th machine at 2am should not be
 * stopped by a billing rule nobody is awake to relax.
 */
class EntitlementLimitTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
    }

    public function test_a_blocking_contract_refuses_a_factory_past_the_limit(): void
    {
        // One factory exists, and the plan covers one.
        $this->contract(['included_factories' => 1, 'overage_policy' => 'BLOCK']);

        $this->expectException(ValidationException::class);

        app(SaveFactory::class)->create([
            'name' => 'Gazipur Unit 2',
            'code' => 'GAZ',
        ]);
    }

    public function test_the_message_says_what_to_do_about_it(): void
    {
        $this->contract(['included_factories' => 1, 'overage_policy' => 'BLOCK']);

        try {
            app(SaveFactory::class)->create(['name' => 'Gazipur Unit 2', 'code' => 'GAZ']);
            $this->fail('The limit did not stop anything.');
        } catch (ValidationException $e) {
            // Read by somebody in a factory, not by whoever signed the
            // contract: it names the limit and who can raise it.
            $this->assertStringContainsString('1 factories', $e->errors()['limit'][0]);
            $this->assertStringContainsString('provider', $e->errors()['limit'][0]);
        }
    }

    public function test_warn_only_lets_the_work_carry_on(): void
    {
        $this->contract(['included_factories' => 1, 'overage_policy' => 'WARN_ONLY']);

        $factory = app(SaveFactory::class)->create(['name' => 'Gazipur Unit 2', 'code' => 'GAZ']);

        // Over the limit and created anyway. Going over is answered on the
        // invoice, not at the keyboard.
        $this->assertNotNull($factory->id);
        $this->assertSame(2, Factory::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $this->delta->id)->count());
    }

    public function test_no_limit_means_no_limit(): void
    {
        // Blocking, but with nothing to block against. Null is not zero, and
        // treating an empty box as a limit of nought would lock a customer out
        // of their own system.
        $this->contract(['included_factories' => null, 'overage_policy' => 'BLOCK']);

        $factory = app(SaveFactory::class)->create(['name' => 'Gazipur Unit 2', 'code' => 'GAZ']);

        $this->assertNotNull($factory->id);
    }

    public function test_a_customer_with_no_contract_is_not_blocked(): void
    {
        // Still being set up. Locking them out while somebody in sales writes
        // a contract would be absurd.
        $factory = app(SaveFactory::class)->create(['name' => 'Gazipur Unit 2', 'code' => 'GAZ']);

        $this->assertNotNull($factory->id);
    }

    public function test_the_user_limit_is_enforced_on_invite(): void
    {
        TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->contract(['included_users' => 1, 'overage_policy' => 'BLOCK']);

        // System roles: company_id is null on all of them.
        $role = Role::withoutGlobalScope(TenantScope::class)
            ->whereNull('company_id')
            ->where('code', 'MAINTENANCE_MANAGER')
            ->firstOrFail();

        $this->expectException(ValidationException::class);

        app(ManageCompanyUser::class)->invite(
            ['email' => 'second@delta.test', 'name' => 'Second Person', 'status' => 'ACTIVE'],
            [$role->id],
            null,
        );
    }

    public function test_limits_can_be_changed_without_a_new_contract(): void
    {
        $this->contract(['included_factories' => 1, 'overage_policy' => 'BLOCK']);

        $staff = User::create([
            'name' => 'Platform Support',
            'email' => 'support@platform.test',
            'password' => 'correct-horse-battery',
            'status' => 'ACTIVE',
            'locale' => 'en',
            'is_platform_admin' => true,
        ]);

        $this->actingAs($staff)
            ->patch('/platform/tenants/'.$this->delta->id.'/limits', [
                'included_factories' => 5,
                'included_assets' => 500,
                'included_users' => 25,
                'overage_policy' => 'WARN_ONLY',
            ])
            ->assertRedirect();

        // One contract still, with new numbers on it. Superseding it would put
        // a contract number in the customer's file that nobody had signed, for
        // a change of one field.
        $contracts = SubscriptionContract::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $this->delta->id)
            ->get();

        $this->assertCount(1, $contracts);
        $this->assertSame(5, $contracts->first()->included_factories);
        $this->assertSame('WARN_ONLY', $contracts->first()->overage_policy);

        // And the change takes effect at once.
        TenantFixture::actingAsTenant($this->delta);
        $this->assertNotNull(app(SaveFactory::class)->create(['name' => 'Gazipur Unit 2', 'code' => 'GAZ'])->id);
    }

    public function test_limits_need_a_contract_to_live_on(): void
    {
        $staff = User::create([
            'name' => 'Platform Support',
            'email' => 'support2@platform.test',
            'password' => 'correct-horse-battery',
            'status' => 'ACTIVE',
            'locale' => 'en',
            'is_platform_admin' => true,
        ]);

        $this->actingAs($staff)
            ->from('/platform/tenants/'.$this->delta->id)
            ->patch('/platform/tenants/'.$this->delta->id.'/limits', [
                'included_factories' => 5,
                'overage_policy' => 'BLOCK',
            ])
            ->assertSessionHasErrors('limits');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function contract(array $overrides): void
    {
        SubscriptionContract::withoutGlobalScope(TenantScope::class)->create($overrides + [
            'company_id' => $this->delta->id,
            'contract_number' => 'SUB-2026-0001',
            'start_date' => now()->startOfMonth()->toDateString(),
            'billing_cycle' => 'MONTHLY',
            'amount' => '51750.0000',
            'currency' => 'BDT',
            'grace_period_days' => 14,
            'status' => 'ACTIVE',
            'auto_renew' => true,
        ]);
    }
}
