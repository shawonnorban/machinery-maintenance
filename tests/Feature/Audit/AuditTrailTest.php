<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Models\Asset;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Costing\Models\CostCategory;
use App\Modules\Costing\Models\CostEntry;
use App\Modules\Identity\Models\LoginAttempt;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The audit trail (SRS 34, ERD Section 18).
 *
 * What makes an audit log worth having is that it is complete, that it is
 * append-only, and that it never becomes a place credentials can be recovered
 * from. Each of those fails silently, which is why each is pinned here.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        // Everything the fixtures wrote is already audited; clear it so each
        // test reads only its own rows.
        AuditLog::query()->getQuery()->delete();
    }

    public function test_creating_a_record_is_audited_with_its_values(): void
    {
        Vendor::create([
            'name' => 'Juki Bangladesh Ltd',
            'code' => 'JUKI-BD',
            'vendor_type' => 'BOTH',
            'status' => 'ACTIVE',
        ]);

        $log = AuditLog::where('entity_type', 'vendors')->firstOrFail();

        $this->assertSame('CREATED', $log->action);
        $this->assertSame('Juki Bangladesh Ltd', $log->new_values_json['name']);
        // The label is captured at write time so a five-year-old row still
        // names something a person recognises.
        $this->assertSame('JUKI-BD', $log->entity_label);
        $this->assertSame($this->delta->id, $log->company_id);
    }

    public function test_an_update_records_only_what_changed(): void
    {
        $vendor = Vendor::create([
            'name' => 'Juki Bangladesh Ltd',
            'code' => 'JUKI-BD',
            'vendor_type' => 'BOTH',
            'status' => 'ACTIVE',
        ]);

        AuditLog::query()->getQuery()->delete();

        $vendor->update(['contact_name' => 'Rafiqul Islam']);

        $log = AuditLog::firstOrFail();

        // Recording the whole row twice would make every diff a wall of
        // unchanged values, and people stop reading it.
        $this->assertSame(['contact_name'], $log->changed_fields_json);
        $this->assertSame(['contact_name' => [null, 'Rafiqul Islam']], $log->diff());
    }

    public function test_a_save_that_changes_nothing_is_not_an_event(): void
    {
        $vendor = Vendor::create([
            'name' => 'Juki Bangladesh Ltd',
            'code' => 'JUKI-BD',
            'vendor_type' => 'BOTH',
            'status' => 'ACTIVE',
        ]);

        AuditLog::query()->getQuery()->delete();

        $vendor->update(['name' => 'Juki Bangladesh Ltd']);

        // Otherwise the trail fills with rows that say "somebody pressed save".
        $this->assertSame(0, AuditLog::count());
    }

    public function test_a_status_change_is_named_as_one(): void
    {
        app(ChangeAssetStatus::class)->handle($this->asset, 'IDLE', null, 'End of shift', 'MANUAL');

        $log = AuditLog::where('entity_type', 'assets')->firstOrFail();

        // SRS 34 names status changes as their own event: somebody asking who
        // put a machine back into service should not have to read the diff of
        // every UPDATED row to find it.
        $this->assertSame('STATUS_CHANGED', $log->action);
        $this->assertSame(['RUNNING', 'IDLE'], $log->diff()['status']);
    }

    public function test_a_cost_change_is_named_as_one(): void
    {
        $entry = CostEntry::create([
            'asset_id' => $this->asset->id,
            'cost_category_id' => CostCategory::whereNull('company_id')->value('id'),
            'amount' => '1000.0000',
            'currency' => 'BDT',
            'exchange_rate' => '1.000000',
            'base_amount' => '1000.0000',
            'occurred_at' => now(),
            'source_type' => 'MANUAL',
            'posted_at' => now(),
        ]);

        AuditLog::query()->getQuery()->delete();

        $entry->update(['amount' => '1500.0000', 'base_amount' => '1500.0000']);

        $this->assertSame('COST_CHANGED', AuditLog::firstOrFail()->action);
    }

    public function test_a_permission_change_is_named_as_one(): void
    {
        $user = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        AuditLog::query()->getQuery()->delete();

        $role = Role::whereNull('company_id')
            ->where('code', 'MAINTENANCE_ENGINEER')
            ->firstOrFail();

        UserRole::where('user_id', $user->id)
            ->firstOrFail()
            ->update(['role_id' => $role->id]);

        // An access change nobody can trace is how a review ends in guesswork.
        $this->assertSame('PERMISSION_CHANGED', AuditLog::firstOrFail()->action);
    }

    public function test_a_failed_sign_in_is_recorded_with_its_reason_and_no_password(): void
    {
        $this->post('/login', ['email' => 'nobody@nowhere.test', 'password' => 'hunter2-secret']);

        $log = AuditLog::where('action', 'LOGIN_FAILED')->firstOrFail();

        $this->assertSame('nobody@nowhere.test', $log->new_values_json['email']);
        $this->assertSame('UNKNOWN_EMAIL', $log->new_values_json['reason']);

        // The submitted password must not be recoverable from the audit log in
        // any form — not the value, not a hash, not a length.
        $this->assertStringNotContainsString('hunter2', json_encode($log->getAttributes()));
    }

    public function test_a_successful_sign_in_is_recorded_once(): void
    {
        $user = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-battery']);

        // One row, not two: the successful attempt row and the Login event
        // describe the same moment.
        $this->assertSame(1, AuditLog::where('action', 'LOGIN')->count());
        $this->assertSame(0, AuditLog::where('action', 'LOGIN_FAILED')->count());
    }

    public function test_a_locked_out_attempt_is_recorded_as_its_own_reason(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->post('/login', ['email' => 'target@delta.test', 'password' => 'wrong']);
        }

        $reasons = AuditLog::where('action', 'LOGIN_FAILED')
            ->get()
            ->map(fn (AuditLog $log) => $log->new_values_json['reason'])
            ->unique()
            ->values()
            ->all();

        // Somebody working through a list of addresses and somebody hitting a
        // rate limit are different findings, and the trail has to tell them
        // apart.
        $this->assertContains('UNKNOWN_EMAIL', $reasons);
        $this->assertContains('RATE_LIMITED', $reasons);
    }

    public function test_credentials_are_never_stored_even_when_a_user_changes_one(): void
    {
        $user = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm2@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        AuditLog::query()->getQuery()->delete();

        $user->update(['password' => 'a-brand-new-secret']);

        $log = AuditLog::where('entity_type', 'users')->firstOrFail();
        $recorded = json_encode($log->getAttributes());

        $this->assertStringNotContainsString('a-brand-new-secret', $recorded);
        $this->assertSame('[redacted]', $log->new_values_json['password']);
    }

    public function test_an_audit_row_cannot_be_changed_or_deleted(): void
    {
        Vendor::create([
            'name' => 'Juki Bangladesh Ltd',
            'code' => 'JUKI-BD',
            'vendor_type' => 'BOTH',
            'status' => 'ACTIVE',
        ]);

        $log = AuditLog::firstOrFail();

        // Enforced on the model rather than left to discipline: the one moment
        // somebody would want to edit an audit row is exactly the moment it
        // matters.
        $updateFailed = false;
        $deleteFailed = false;

        try {
            // A different value on purpose: writing back the same one is not a
            // change at all, so Eloquent would skip the save and the guard
            // would never run.
            $log->update(['action' => 'DELETED']);
        } catch (RuntimeException $e) {
            $updateFailed = str_contains($e->getMessage(), 'append-only');
        }

        try {
            $log->delete();
        } catch (RuntimeException $e) {
            $deleteFailed = str_contains($e->getMessage(), 'append-only');
        }

        $this->assertTrue($updateFailed, 'An audit row was updated.');
        $this->assertTrue($deleteFailed, 'An audit row was deleted.');
        $this->assertSame('CREATED', $log->fresh()->action);
    }

    public function test_rows_written_by_one_request_share_a_request_id(): void
    {
        // The store manager holds vendor.vendor.create; the supplier list is
        // purchasing's.
        $user = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($user)->post('/app/vendors', [
            'name' => 'Brother Service BD',
            'code' => 'BROTHER-BD',
            'vendor_type' => 'SERVICE',
            'status' => 'ACTIVE',
        ]);

        $log = AuditLog::where('entity_type', 'vendors')->firstOrFail();

        // One support ticket citing a request id resolves to the whole causal
        // chain (ADR-061).
        $this->assertNotNull($log->request_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertNotNull($log->ip_address);
    }

    public function test_a_cross_tenant_request_is_recorded_as_a_security_event(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');

        $user = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm3@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        AuditLog::query()->getQuery()->delete();

        $this->actingAs($user)
            ->withHeader('X-Company-Id', $other->id)
            ->get('/app/dashboard')
            ->assertForbidden();

        $log = AuditLog::where('action', 'SECURITY_EVENT')->firstOrFail();

        // Either a bug or an attempt, and both need to be visible.
        $this->assertSame('TENANT_ACCESS_DENIED', $log->new_values_json['reason']);
        $this->assertSame($other->id, $log->new_values_json['requested_company_id']);
        $this->assertSame($user->id, $log->user_id);
    }

    public function test_the_ledger_is_not_audited_twice(): void
    {
        // Inventory transactions, cost entries and status histories are already
        // append-only records of what happened. Auditing them again would bury
        // the decisions a person made under rows the system wrote to itself.
        $this->assertSame(0, AuditLog::where('entity_type', 'inventory_transactions')->count());
        $this->assertSame(0, AuditLog::where('entity_type', 'asset_status_histories')->count());
    }

    public function test_an_attempt_for_an_unknown_email_still_produces_a_row(): void
    {
        LoginAttempt::create([
            'email' => 'stranger@example.test',
            'ip_address' => '203.0.113.9',
            'successful' => false,
            'failure_reason' => 'UNKNOWN_EMAIL',
            'attempted_at' => now(),
        ]);

        $log = AuditLog::where('action', 'LOGIN_FAILED')->firstOrFail();

        // It belongs to no company, and dropping rows for want of a tenant
        // would hide exactly the pattern worth seeing.
        $this->assertNull($log->company_id);
        $this->assertNull($log->user_id);
        $this->assertSame('stranger@example.test', $log->entity_label);
    }
}
