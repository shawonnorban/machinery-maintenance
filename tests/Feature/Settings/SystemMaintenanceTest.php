<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Settings\Listeners\RecordScheduledJobRuns;
use App\Modules\Settings\Models\CompanyBackup;
use App\Modules\Settings\Models\Currency;
use App\Modules\Settings\Models\IntegrationLog;
use App\Modules\Settings\Models\ScheduledJobRun;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\TenantFixture;
use Tests\TestCase;
use ZipArchive;

/**
 * Settings → System & Maintenance (AnnotechERP 03/04): notification defaults,
 * the scheduler's run log, backup & restore, system health and data retention.
 */
class SystemMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Company $other;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        $this->other = TenantFixture::company('Other Garments Ltd', 'OGL');
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_a_locked_company_default_overrides_a_persons_own_choice(): void
    {
        NotificationPreference::query()->create(['company_id' => $this->delta->id, 'user_id' => $this->owner->id, 'event_type' => 'LOW_STOCK', 'in_app' => true, 'email' => false, 'sms' => false, 'whatsapp' => false]);
        $dispatcher = app(NotificationDispatcher::class);

        // Unlocked: the person's own "no email" wins over the company's "email".
        $rows = $this->as()->putJson('/api/v1/settings/notification-defaults', ['defaults' => ['LOW_STOCK' => ['email' => true, 'sms' => true]]])->assertOk()->json('data');
        $this->assertFalse(collect($rows)->contains('event_type', 'PLATFORM_TICKET_OPENED'));
        $this->assertFalse($dispatcher->preferenceFor($this->owner, 'LOW_STOCK')->email);

        // Locked: the company's choice applies, and the person cannot change it.
        $this->as()->putJson('/api/v1/settings/notification-defaults', ['defaults' => ['LOW_STOCK' => ['email' => true, 'sms' => true, 'locked' => true]]])->assertOk();
        $this->assertTrue($dispatcher->preferenceFor($this->owner, 'LOW_STOCK')->email);
        $this->assertTrue($dispatcher->preferenceFor($this->owner, 'LOW_STOCK')->sms);
        $this->as()->patchJson('/api/v1/notification-preferences', ['preferences' => ['LOW_STOCK' => ['email' => false]]])
            ->assertOk()->assertJsonPath('data.LOW_STOCK.email', true)->assertJsonPath('data.LOW_STOCK.locked', true);

        // An event nobody configured still follows the built-in default.
        $this->assertTrue($dispatcher->preferenceFor($this->owner, 'BREAKDOWN_CRITICAL')->email);

        $this->as()->deleteJson('/api/v1/settings/notification-defaults/LOW_STOCK')->assertOk();
        $this->assertFalse($dispatcher->preferenceFor($this->owner, 'LOW_STOCK')->email);
    }

    public function test_the_scheduler_run_log_records_status_but_never_output(): void
    {
        $jobs = collect($this->as()->getJson('/api/v1/settings/scheduled-jobs')->assertOk()->json('data'));
        $this->assertNotNull($jobs->firstWhere('command', 'settings:purge-retention'));
        $this->assertSame('0 3 * * *', $jobs->firstWhere('command', 'settings:purge-retention')['expression']);

        $task = new ScheduledEvent(app(CacheEventMutex::class), "'/usr/bin/php' 'artisan' webhooks:retry");
        $task->everyFiveMinutes();
        $listener = new RecordScheduledJobRuns;
        $listener->onStarting(new ScheduledTaskStarting($task));
        $task->exitCode = 1;
        $listener->onFinished(new ScheduledTaskFinished($task, 0.2));

        $run = ScheduledJobRun::query()->sole();
        $this->assertSame(['webhooks:retry', 'FAILED', 1], [$run->command, $run->status, $run->exit_code]);
        $this->assertNotContains('output', array_keys($run->getAttributes()));

        $this->as()->getJson('/api/v1/settings/scheduled-jobs/runs?status=FAILED')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('FAILED', collect($this->as()->getJson('/api/v1/settings/scheduled-jobs')->json('data'))->firstWhere('command', 'webhooks:retry')['last_status']);
    }

    public function test_a_backup_holds_only_this_companys_rows_and_no_secrets(): void
    {
        $this->otherCurrency();
        $this->as()->postJson('/api/v1/erp/currencies', ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 110])->assertCreated();

        $this->as()->postJson('/api/v1/settings/backups')->assertCreated();
        TenantFixture::actingAsTenant($this->delta);
        $backup = CompanyBackup::query()->sole();
        $this->assertSame('READY', $backup->status, (string) $backup->error);

        $zip = $this->openZip($backup->file_path);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $this->assertSame($this->delta->id, $manifest['company_id']);
        $this->assertArrayHasKey('currencies', $manifest['tables']);
        foreach (['users', 'api_tokens', 'api_clients', 'personal_access_tokens', 'subscription_invoices', 'company_users'] as $secret) {
            $this->assertArrayNotHasKey($secret, $manifest['tables']);
        }
        $currencies = (string) $zip->getFromName('tables/currencies.jsonl');
        $this->assertStringContainsString('"USD"', $currencies);
        $this->assertStringNotContainsString('"EUR"', $currencies);
        $zip->close();

        $this->get("/api/v1/settings/backups/{$backup->id}/download", ['Authorization' => 'Bearer '.$this->token()])->assertOk();
        // One at a time.
        CompanyBackup::query()->create(['company_id' => $this->delta->id, 'status' => 'RUNNING']);
        $this->as()->postJson('/api/v1/settings/backups')->assertUnprocessable();
    }

    public function test_a_restore_brings_deleted_rows_back_keeps_new_ones_and_takes_a_safety_backup(): void
    {
        $usd = $this->as()->postJson('/api/v1/erp/currencies', ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 110])->json('data.id');
        $this->as()->postJson('/api/v1/settings/backups')->assertCreated();
        TenantFixture::actingAsTenant($this->delta);
        $backup = CompanyBackup::query()->sole();

        $this->as()->patchJson("/api/v1/erp/currencies/{$usd}", ['name' => 'Changed'])->assertOk();
        $this->as()->postJson('/api/v1/erp/currencies', ['code' => 'GBP', 'name' => 'Pound', 'symbol' => '£', 'decimal_places' => 2, 'exchange_rate' => 140])->assertCreated();

        $restore = $this->upload($backup->file_path)->assertCreated()->assertJsonPath('data.status', 'PREVIEWED')->json('data');
        $this->assertSame('DAL', $restore['company_code']);
        $this->assertGreaterThanOrEqual(1, collect($restore['preview'])->firstWhere('table', 'currencies')['existing']);

        $this->as()->postJson("/api/v1/settings/restores/{$restore['id']}/apply", ['confirm_code' => 'XYZ'])->assertUnprocessable();
        $this->as()->postJson("/api/v1/settings/restores/{$restore['id']}/apply", ['confirm_code' => 'dal'])->assertOk();

        TenantFixture::actingAsTenant($this->delta);
        $this->assertSame('APPLIED', $this->as()->getJson('/api/v1/settings/restores')->json('data.0.status'), (string) $this->as()->getJson('/api/v1/settings/restores')->json('data.0.error'));
        $this->assertSame('US Dollar', Currency::query()->findOrFail($usd)->name);
        $this->assertTrue(Currency::query()->where('code', 'GBP')->exists());
        $this->assertTrue(CompanyBackup::query()->where('kind', 'PRE_RESTORE')->where('status', 'READY')->exists());
    }

    public function test_a_changed_or_foreign_backup_is_refused(): void
    {
        $this->as()->postJson('/api/v1/erp/currencies', ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 110])->assertCreated();
        $this->as()->postJson('/api/v1/settings/backups')->assertCreated();
        TenantFixture::actingAsTenant($this->delta);
        $backup = CompanyBackup::query()->sole();

        // A row slipped in by hand: the checksum and the signature no longer match.
        $tampered = 'backups/tampered.zip';
        Storage::disk('local')->put($tampered, Storage::disk('local')->get($backup->file_path));
        $zip = $this->openZip($tampered);
        $zip->addFromString('tables/currencies.jsonl', (string) $zip->getFromName('tables/currencies.jsonl').json_encode(['id' => (string) str()->ulid(), 'company_id' => $this->delta->id, 'code' => 'XXX'])."\n");
        $zip->close();
        $this->upload($tampered)->assertCreated()->assertJsonPath('data.status', 'INVALID');

        // A genuine backup of another company is still another company's.
        $theirs = TenantFixture::user($this->other, 'COMPANY_OWNER', 'owner@other.test');
        TenantFixture::actingAsTenant($this->other);
        $this->withToken($this->token($theirs, $this->other))->postJson('/api/v1/settings/backups')->assertCreated();
        TenantFixture::actingAsTenant($this->other);
        $foreign = CompanyBackup::query()->sole()->file_path;
        TenantFixture::actingAsTenant($this->delta);
        $this->upload($foreign)->assertCreated()->assertJsonPath('data.status', 'INVALID');
    }

    public function test_restoring_is_the_owners_alone(): void
    {
        $admin = TenantFixture::user($this->delta, 'COMPANY_ADMIN', 'admin@delta.test');
        $this->withToken($this->token($admin))->getJson('/api/v1/settings/restores')->assertForbidden();
        $this->withToken($this->token($admin))->getJson('/api/v1/settings/backups')->assertOk();
    }

    public function test_the_health_cards_say_nothing_secret(): void
    {
        $body = $this->as()->getJson('/api/v1/settings/system-health')->assertOk()->getContent();
        $cards = collect(json_decode($body, true)['data'])->keyBy('key');
        $this->assertSame(['database', 'cache', 'queue', 'storage', 'scheduler', 'mail', 'sms', 'backup', 'app'], $cards->keys()->all());
        $this->assertSame('OK', $cards['database']['status']);
        $this->assertSame('DOWN', $cards['scheduler']['status']);
        foreach ([(string) config('database.connections.pgsql.host'), (string) config('database.connections.pgsql.password'), (string) config('app.key')] as $secret) {
            if ($secret !== '') {
                $this->assertStringNotContainsString($secret, $body);
            }
        }
    }

    public function test_a_purge_removes_only_this_companys_old_rows(): void
    {
        $old = CarbonImmutable::now()->subDays(120);
        foreach ([$this->delta, $this->other] as $company) {
            TenantFixture::actingAsTenant($company);
            IntegrationLog::query()->create(['company_id' => $company->id, 'channel' => 'SMS', 'event' => 'SMS_TEST', 'status' => 'SUCCEEDED', 'occurred_at' => $old]);
            IntegrationLog::query()->create(['company_id' => $company->id, 'channel' => 'SMS', 'event' => 'SMS_TEST', 'status' => 'SUCCEEDED', 'occurred_at' => CarbonImmutable::now()]);
        }
        TenantFixture::actingAsTenant($this->delta);

        $preview = collect($this->as()->getJson('/api/v1/settings/retention')->assertOk()->json('data'))->keyBy('category');
        $this->assertSame(1, $preview['INTEGRATION_LOGS']['due']);

        $this->as()->putJson('/api/v1/settings/retention', ['policies' => ['AUDIT_LOGS' => ['keep_days' => 30]]])->assertUnprocessable();
        $this->as()->putJson('/api/v1/settings/retention', ['policies' => ['INTEGRATION_LOGS' => ['keep_days' => 200]]])->assertOk();
        $this->assertSame(0, collect($this->as()->getJson('/api/v1/settings/retention')->json('data'))->firstWhere('category', 'INTEGRATION_LOGS')['due']);
        $this->as()->putJson('/api/v1/settings/retention', ['policies' => ['INTEGRATION_LOGS' => ['keep_days' => 90]]])->assertOk();

        $this->as()->postJson('/api/v1/settings/retention/purge')->assertOk()->assertJsonPath('data.removed.INTEGRATION_LOGS', 1);
        $this->assertSame(1, IntegrationLog::query()->count());
        TenantFixture::actingAsTenant($this->other);
        $this->assertSame(2, IntegrationLog::query()->count());
    }

    private function otherCurrency(): void
    {
        TenantFixture::actingAsTenant($this->other);
        Currency::query()->create(['company_id' => $this->other->id, 'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'exchange_rate' => 120]);
        TenantFixture::actingAsTenant($this->delta);
    }

    private function openZip(string $path): ZipArchive
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($path)));

        return $zip;
    }

    private function upload(string $path): TestResponse
    {
        $copy = tempnam(sys_get_temp_dir(), 'bk').'.zip';
        copy(Storage::disk('local')->path($path), $copy);

        return $this->withToken($this->token())->post('/api/v1/settings/restores', ['file' => new UploadedFile($copy, 'backup.zip', 'application/zip', null, true)], ['Accept' => 'application/json']);
    }

    private function token(?User $user = null, ?Company $company = null): string
    {
        return app(IssueApiToken::class)->forUser($user ?? $this->owner, ($company ?? $this->delta)->id, 'Test token')['plain'];
    }

    private function as(): static
    {
        return $this->withToken($this->token());
    }
}
