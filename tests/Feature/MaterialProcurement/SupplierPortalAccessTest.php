<?php

declare(strict_types=1);

namespace Tests\Feature\MaterialProcurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Services\UsageMeter;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Material Procurement → Control → Supplier Portal Access (AnnotechERP 08/07):
 * a supplier login is an external account in the company holding only the
 * Supplier role, linked to its supplier; it hears of every requisition
 * submitted while it is active, reaches nothing of the company's own, and
 * is switched off and on from the list.
 */
class SupplierPortalAccessTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_a_supplier_login_is_an_external_account_that_only_reaches_the_portal_and_hears_of_requirements(): void
    {
        $supplier = $this->supplier('SUP-002', 'Poly Plan Ltd');
        $url = '/api/v1/material-procurement/supplier-logins';
        $this->assertSame([['value' => $supplier, 'label' => 'Poly Plan Ltd (SUP-002)']], $this->as()->getJson($url)->assertOk()->json('data.suppliers'));

        $this->as()->postJson($url, [])->assertUnprocessable()
            ->assertJsonPath('errors.supplier_id.0', 'Supplier is required.')->assertJsonPath('errors.contact_name.0', 'Contact name is required.')
            ->assertJsonPath('errors.email.0', 'Sign-in email is required.')->assertJsonPath('errors.password.0', 'Initial password is required.');
        $form = ['supplier_id' => $supplier, 'contact_name' => 'Nasir Uddin', 'email' => 'nasir@polyplan.test', 'password' => 'Quote-Ready-2026!'];
        $this->as()->postJson($url, ['password' => 'short'] + $form)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->as()->postJson($url, ['password' => 'alllowercase-and-long-1!'] + $form)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->as()->postJson($url, ['email' => 'owner@delta.test'] + $form)->assertUnprocessable()->assertJsonPath('errors.email.0', 'This email already has an account.');

        $login = $this->as()->postJson($url, $form)->assertCreated()->json('data');
        $this->assertSame(['Poly Plan Ltd', 'SUP-002', 'Nasir Uddin', 'nasir@polyplan.test', true, null], [$login['supplier'], $login['supplier_code'], $login['contact_name'], $login['email'], $login['active'], $login['last_sign_in']]);
        $user = User::query()->where('email', 'nasir@polyplan.test')->firstOrFail();
        $this->assertSame('EXTERNAL', $user->user_type);
        // The initial password is changed at the first sign-in.
        $this->assertTrue($user->must_change_password);
        $this->assertSame('ACTIVE', CompanyUser::withoutGlobalScope(TenantScope::class)->where('user_id', $user->id)->value('status'));
        $this->assertSame(1, $this->as()->getJson($url)->json('data.active'));
        // The supplier master counts its active logins.
        $this->assertSame(1, collect($this->as()->getJson('/api/v1/erp/material-suppliers')->json('data'))->firstWhere('id', $supplier)['portal_logins']);

        // Only the portal: nothing of the company's own, not even raising a request.
        $granted = $this->as($user)->getJson('/api/v1/auth/permissions')->assertOk()->json('data.permissions');
        $this->assertSame(['material.portal.view'], $granted);
        $this->as($user)->getJson('/api/v1/material-procurement/requisitions')->assertForbidden();
        $this->as($user)->getJson($url)->assertForbidden();

        // A submitted requisition is announced to every active login.
        $pr = $this->submittedRequisition();
        $note = Notification::query()->withoutGlobalScopes()->where('user_id', $user->id)->where('event_type', 'MP_REQUIREMENT_AVAILABLE')->sole();
        $this->assertSame(['pr' => $pr, 'lines' => 1], array_intersect_key($note->data_json, ['pr' => 1, 'lines' => 1]));

        // Switched off: the account is suspended here and hears of nothing more.
        $this->as()->putJson("{$url}/{$login['id']}/active", ['active' => false])->assertOk()->assertJsonPath('data.active', false);
        $this->assertSame('SUSPENDED', CompanyUser::withoutGlobalScope(TenantScope::class)->where('user_id', $user->id)->value('status'));
        $this->assertSame(0, $this->as()->getJson($url)->json('data.active'));
        $this->submittedRequisition();
        $this->assertSame(1, Notification::query()->withoutGlobalScopes()->where('user_id', $user->id)->count());
        $this->as()->putJson("{$url}/{$login['id']}/active", ['active' => true])->assertOk()->assertJsonPath('data.active', true);
        $this->assertSame('ACTIVE', CompanyUser::withoutGlobalScope(TenantScope::class)->where('user_id', $user->id)->value('status'));

        // The store manager raises requisitions but does not hand out portal logins.
        $manager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'stores@delta.test');
        $this->as($manager)->getJson($url)->assertForbidden();
    }

    public function test_a_supplier_login_takes_no_subscription_seat(): void
    {
        $supplier = $this->supplier('SUP-004', 'Seat Free Trims');
        $seats = CompanyUser::seatCount($this->delta->id);
        // Every seat taken: a supplier login is still created.
        SubscriptionContract::withoutGlobalScope(TenantScope::class)->create([
            'company_id' => $this->delta->id, 'contract_number' => 'SUB-2026-0001', 'start_date' => now()->startOfMonth()->toDateString(), 'billing_cycle' => 'MONTHLY',
            'amount' => '51750.0000', 'currency' => 'BDT', 'grace_period_days' => 14, 'status' => 'ACTIVE', 'auto_renew' => true, 'included_users' => $seats, 'overage_policy' => 'BLOCK',
        ]);
        $form = ['supplier_id' => $supplier, 'contact_name' => 'Rina Akter', 'email' => 'rina@seatfree.test', 'password' => 'Quote-Ready-2026!'];
        $this->as()->postJson('/api/v1/material-procurement/supplier-logins', $form)->assertCreated();
        $this->assertSame($seats, CompanyUser::seatCount($this->delta->id));
        // Nor does the monthly usage count it.
        $this->assertSame((string) $seats, app(UsageMeter::class)->measure($this->delta->id)->firstWhere('metric', 'ACTIVE_USERS')->value);

        // A staff member holding the Supplier role besides their own does take a seat.
        $user = User::query()->where('email', 'rina@seatfree.test')->firstOrFail();
        $storekeeper = Role::query()->whereNull('company_id')->where('name', 'STOREKEEPER')->firstOrFail();
        UserRole::query()->withoutGlobalScopes()->create(['company_id' => $this->delta->id, 'user_id' => $user->id, 'role_id' => $storekeeper->id]);
        $this->assertSame($seats + 1, CompanyUser::seatCount($this->delta->id));
    }

    public function test_an_inactive_supplier_gets_no_login(): void
    {
        $supplier = $this->supplier('SUP-003', 'Closed Trims');
        $this->as()->patchJson("/api/v1/erp/material-suppliers/{$supplier}", ['active' => false])->assertOk();
        $this->as()->postJson('/api/v1/material-procurement/supplier-logins', ['supplier_id' => $supplier, 'contact_name' => 'X', 'email' => 'x@closed.test', 'password' => 'Quote-Ready-2026!'])
            ->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
        $this->assertSame([], $this->as()->getJson('/api/v1/material-procurement/supplier-logins')->json('data.suppliers'));
    }

    private function supplier(string $code, string $name): string
    {
        return $this->as()->postJson('/api/v1/erp/material-suppliers', ['code' => $code, 'name' => $name, 'supplier_type' => 'LOCAL_NON_EPZ', 'goods_service' => 'Poly'])->assertCreated()->json('data.id');
    }

    /** A direct requisition of one fabric line, submitted; its number. */
    private function submittedRequisition(): string
    {
        static $item = null;
        $item ??= $this->as()->postJson('/api/v1/erp/material-fabrics', ['item_code' => 'FAB-001', 'name' => 'Cotton Jersey', 'category' => 'Knit', 'uom' => 'Kg'])->assertCreated()->json('data.id');

        return $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'submit' => true, 'lines' => [['kind' => 'FABRIC', 'item_id' => $item, 'req_qty' => 10, 'uom' => 'KG', 'req_date' => '2026-11-20']]])
            ->assertCreated()->json('data.pr_no');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
