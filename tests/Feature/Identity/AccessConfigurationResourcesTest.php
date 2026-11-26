<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\MailService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The screen kit's resources (`/erp/{resource}`) behind Access Control's
 * Configuration and Operations screens (AnnotechERP 02/03, 02/04).
 */
class AccessConfigurationResourcesTest extends TestCase
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
    }

    public function test_a_report_template_is_created_unique_by_code_edited_and_deleted(): void
    {
        $this->as($this->owner)->postJson('/api/v1/erp/report-templates', ['name' => 'PO print', 'output_format' => 'PDF'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Template Code is required.');

        $id = $this->as($this->owner)->postJson('/api/v1/erp/report-templates', ['code' => 'rpt-po', 'name' => 'PO print', 'output_format' => 'PDF', 'paper_size' => 'A4'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'RPT-PO')
            ->assertJsonPath('data.active', true)
            ->json('data.id');

        $this->as($this->owner)->postJson('/api/v1/erp/report-templates', ['code' => 'RPT-PO', 'name' => 'Again', 'output_format' => 'CSV'])->assertUnprocessable();
        $this->as($this->owner)->patchJson("/api/v1/erp/report-templates/{$id}", ['orientation' => 'LANDSCAPE', 'active' => '0'])
            ->assertOk()
            ->assertJsonPath('data.orientation', 'LANDSCAPE')
            ->assertJsonPath('data.active', false);

        // Another company's id is not found.
        $gamma = TenantFixture::company('Gamma Knit Ltd', 'GKL');
        $stranger = TenantFixture::user($gamma, 'COMPANY_OWNER', 'owner@gamma.test');
        $this->as($stranger, $gamma)->getJson("/api/v1/erp/report-templates/{$id}")->assertNotFound();

        $this->as($this->owner)->deleteJson("/api/v1/erp/report-templates/{$id}")->assertNoContent();
    }

    public function test_a_mail_relay_names_its_password_variable_and_one_is_the_default(): void
    {
        $relay = ['smtp_host' => 'smtp.delta.test', 'port' => 587, 'smtp_username' => 'erp', 'encryption' => 'TLS', 'from_name' => 'Delta ERP', 'from_address' => 'no-reply@delta.test'];

        $this->as($this->owner)->postJson('/api/v1/erp/mail-services', $relay + ['service_name' => 'Orders'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password_env.0', 'Password Environment Variable is required.');
        // A password pasted in where the variable name belongs is refused.
        $this->as($this->owner)->postJson('/api/v1/erp/mail-services', $relay + ['service_name' => 'Orders', 'password_env' => 'hunter2!'])->assertUnprocessable();
        $this->as($this->owner)->postJson('/api/v1/erp/mail-services', $relay + ['service_name' => 'Orders', 'password_env' => 'SMTP_PASSWORD', 'to_mail' => 'not an email'])->assertUnprocessable();

        $first = $this->as($this->owner)->postJson('/api/v1/erp/mail-services', $relay + ['service_name' => 'Orders', 'password_env' => 'SMTP_PASSWORD', 'is_default' => true, 'to_mail' => 'a@delta.test, b@delta.test'])
            ->assertCreated()->json('data.id');
        $second = $this->as($this->owner)->postJson('/api/v1/erp/mail-services', $relay + ['service_name' => 'Payroll', 'password_env' => 'PAYROLL_SMTP', 'is_default' => true])
            ->assertCreated()->json('data.id');

        TenantFixture::actingAsTenant($this->delta);
        $this->assertFalse(MailService::findOrFail($first)->is_default);
        $this->assertTrue(MailService::findOrFail($second)->is_default);
    }

    public function test_a_violation_names_a_known_business_rule_and_who_raised_it(): void
    {
        $this->as($this->owner)->postJson('/api/v1/erp/rule-violations', ['rule_code' => 'BR-999', 'document_reference' => 'PO-1', 'severity' => 'HIGH', 'detail' => 'x'])->assertUnprocessable();

        $this->as($this->owner)->postJson('/api/v1/erp/rule-violations', ['rule_code' => 'BR-001', 'document_reference' => 'PO-261024', 'severity' => 'HIGH', 'detail' => 'PO above requisition value'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'OPEN')
            ->assertJsonPath('data.raised_by_name', $this->owner->name);

        $this->as($this->owner)->getJson('/api/v1/erp/rule-violations?filter[rule_code]=BR-001')->assertJsonCount(1, 'data');
        $this->as($this->owner)->getJson('/api/v1/erp/rule-violations?filter[rule_code]=BR-002')->assertJsonCount(0, 'data');
    }

    public function test_a_broadcast_reaches_its_audience_once(): void
    {
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test', $this->dhaka->id);
        TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test', $this->dhaka->id);
        $base = ['notification_type' => 'MAINTENANCE', 'message' => 'Down on Friday 22:00', 'message_time' => now()->toDateString(), 'severity' => 'HIGH'];

        $id = $this->as($this->owner)->postJson('/api/v1/erp/broadcasts', $base + ['title' => 'Maintenance window', 'audience' => 'ALL'])
            ->assertCreated()
            ->assertJsonPath('data.recipients_count', 3)
            ->json('data.id');
        $this->assertSame(3, Notification::withoutGlobalScope(TenantScope::class)->where('event_type', 'GLOBAL_BROADCAST')->count());
        $this->assertSame('Maintenance window', Notification::withoutGlobalScope(TenantScope::class)->where('user_id', $keeper->id)->value('title'));

        // Editing the record sends nothing again.
        $this->as($this->owner)->patchJson("/api/v1/erp/broadcasts/{$id}", ['title' => 'Renamed'])->assertOk();
        $this->assertSame(3, Notification::withoutGlobalScope(TenantScope::class)->where('event_type', 'GLOBAL_BROADCAST')->count());

        // A role audience; an inactive broadcast sends nothing.
        $role = Role::where('name', 'STOREKEEPER')->whereNull('company_id')->firstOrFail();
        $this->as($this->owner)->postJson('/api/v1/erp/broadcasts', $base + ['title' => 'Stores only', 'audience' => 'ROLE', 'audience_id' => (string) $role->id])
            ->assertCreated()->assertJsonPath('data.recipients_count', 1);
        $this->as($this->owner)->postJson('/api/v1/erp/broadcasts', $base + ['title' => 'Draft', 'audience' => 'ALL', 'active' => false])
            ->assertCreated()->assertJsonPath('data.recipients_count', 0);
        $this->as($this->owner)->postJson('/api/v1/erp/broadcasts', $base + ['title' => 'Nobody', 'audience' => 'USER'])->assertUnprocessable();
    }

    public function test_a_deletion_request_records_who_asked(): void
    {
        $this->as($this->owner)->postJson('/api/v1/erp/deletion-requests', ['document_type' => 'GENERAL_PO', 'reason' => 'Wrong buyer'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.document_no.0', 'Document No is required.');

        $this->as($this->owner)->postJson('/api/v1/erp/deletion-requests', ['document_type' => 'GENERAL_PO', 'document_no' => 'PO-261024', 'reason' => 'Wrong buyer'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.requested_by_name', $this->owner->name);
    }

    public function test_resources_are_gated_and_unknown_ones_are_not_found(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech2@delta.test', $this->dhaka->id);

        $this->as($technician)->getJson('/api/v1/erp/mail-services')->assertForbidden();
        $this->as($technician)->postJson('/api/v1/erp/broadcasts', [])->assertForbidden();
        $this->as($this->owner)->getJson('/api/v1/erp/no-such-thing')->assertNotFound();
    }

    private function as(User $user, ?Company $company = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, ($company ?? $this->delta)->id, 'Test token');

        return $this->withToken($plain);
    }
}
