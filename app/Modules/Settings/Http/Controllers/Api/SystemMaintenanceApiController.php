<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers\Api;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationEventDefault;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Settings\Jobs\ApplyCompanyRestore;
use App\Modules\Settings\Jobs\RunCompanyBackup;
use App\Modules\Settings\Models\CompanyBackup;
use App\Modules\Settings\Models\CompanyRestore;
use App\Modules\Settings\Models\RetentionPolicy;
use App\Modules\Settings\Models\ScheduledJobRun;
use App\Modules\Settings\Services\CompanyBackupWriter;
use App\Modules\Settings\Services\CompanyRestorer;
use App\Modules\Settings\Services\RetentionPurger;
use App\Modules\Settings\Services\ScheduleCatalog;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Settings\Services\SystemHealth;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Settings → System & Maintenance (AnnotechERP 03/04): notification defaults,
 * scheduled jobs, backup & restore, system health, and data retention.
 */
class SystemMaintenanceApiController extends ApiController
{
    public function __construct(private readonly AuditRecorder $audit) {}

    // ── Notification Preferences ────────────────────────────────────────

    public function notificationDefaults(): JsonResponse
    {
        $this->allow('settings.company.manage');

        return ApiResponse::ok($this->defaultRows());
    }

    public function saveNotificationDefaults(Request $request): JsonResponse
    {
        $this->allow('settings.company.manage');

        $data = $request->validate([
            'defaults' => ['required', 'array'],
            'defaults.*.email' => ['sometimes', 'boolean'],
            'defaults.*.sms' => ['sometimes', 'boolean'],
            'defaults.*.whatsapp' => ['sometimes', 'boolean'],
            'defaults.*.locked' => ['sometimes', 'boolean'],
        ]);
        $events = array_flip($this->tenantEvents());
        foreach ($data['defaults'] as $event => $channels) {
            if (! isset($events[$event])) {
                continue;
            }
            NotificationEventDefault::query()->updateOrCreate(['event_type' => $event], [
                'company_id' => $this->caller()->companyId,
                'email' => (bool) ($channels['email'] ?? false),
                'sms' => (bool) ($channels['sms'] ?? false),
                'whatsapp' => (bool) ($channels['whatsapp'] ?? false),
                'locked' => (bool) ($channels['locked'] ?? false),
            ]);
        }

        return ApiResponse::ok($this->defaultRows());
    }

    /** Back to the built-in default for one event. */
    public function resetNotificationDefault(string $event): JsonResponse
    {
        $this->allow('settings.company.manage');

        NotificationEventDefault::query()->where('event_type', $event)->delete();

        return ApiResponse::ok($this->defaultRows());
    }

    // ── Scheduled Jobs ──────────────────────────────────────────────────

    public function scheduledJobs(ScheduleCatalog $catalog, SettingsResolver $settings): JsonResponse
    {
        $this->allow('settings.company.manage');

        return ApiResponse::ok($catalog->jobs($settings->string('locale.timezone') ?: (string) config('app.timezone')));
    }

    public function jobRuns(Request $request): JsonResponse
    {
        $this->allow('settings.company.manage');

        $data = $request->validate(['command' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:'.implode(',', ScheduledJobRun::STATUSES)]]);

        return ApiResponse::ok(ScheduledJobRun::query()
            ->when($data['command'] ?? null, fn ($q, $c) => $q->where('command', $c))
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest('started_at')
            ->limit(500)
            ->get()
            ->map(fn (ScheduledJobRun $r): array => [
                'id' => $r->id, 'command' => $r->command, 'expression' => $r->expression, 'status' => $r->status, 'exit_code' => $r->exit_code,
                'started_at' => $r->started_at?->toIso8601String(), 'finished_at' => $r->finished_at?->toIso8601String(), 'duration_ms' => $r->duration_ms,
            ])
            ->all());
    }

    // ── Backup & Restore ────────────────────────────────────────────────

    public function backups(): JsonResponse
    {
        $this->allow('settings.company.manage');

        return ApiResponse::ok(CompanyBackup::query()->latest()->limit(200)->get()->map(fn (CompanyBackup $b): array => $this->backupRow($b))->all());
    }

    public function createBackup(): JsonResponse
    {
        $this->allow('settings.company.manage');

        if (CompanyBackup::query()->whereIn('status', ['QUEUED', 'RUNNING'])->exists()) {
            throw ValidationException::withMessages(['backup' => __('settings.system.backup.busy')]);
        }
        $backup = CompanyBackup::query()->create(['company_id' => $this->caller()->companyId, 'kind' => 'MANUAL', 'status' => 'QUEUED', 'requested_by' => $this->caller()->user?->id]);
        $this->audit->record('BACKUP_REQUESTED', $backup, label: $backup->id);
        RunCompanyBackup::dispatch($backup->id, $this->caller()->companyId);

        return ApiResponse::created($this->backupRow($backup->refresh()));
    }

    public function downloadBackup(string $backup): StreamedResponse
    {
        $this->allow('settings.company.manage');

        $row = CompanyBackup::query()->whereKey($backup)->firstOrFail();
        abort_unless($row->status === 'READY' && $row->file_path && Storage::disk(CompanyBackupWriter::DISK)->exists($row->file_path), 404);
        $this->audit->record('BACKUP_DOWNLOADED', $row, label: $row->id);
        $code = Str::slug((string) Company::query()->whereKey($row->company_id)->value('code'));

        return Storage::disk(CompanyBackupWriter::DISK)->download($row->file_path, "backup-{$code}-{$row->created_at->format('Ymd-His')}.zip");
    }

    public function restores(): JsonResponse
    {
        $this->allow('settings.backup.restore');

        return ApiResponse::ok(CompanyRestore::query()->latest()->limit(100)->get()->map(fn (CompanyRestore $r): array => $this->restoreRow($r))->all());
    }

    /** Upload and check: nothing is written to the company's data here. */
    public function uploadRestore(Request $request, CompanyRestorer $restorer): JsonResponse
    {
        $this->allow('settings.backup.restore');

        $request->validate(['file' => ['required', 'file', 'mimes:zip', 'max:102400']]);
        $companyId = $this->caller()->companyId;
        $file = $request->file('file');
        $restore = CompanyRestore::query()->create([
            'company_id' => $companyId,
            'file_path' => $file->storeAs("restores/{$companyId}", Str::ulid().'.zip', CompanyBackupWriter::DISK),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'status' => 'INVALID',
            'requested_by' => $this->caller()->user?->id,
        ]);
        $restore = $restorer->inspect($restore);
        $this->audit->record('RESTORE_UPLOADED', $restore, label: $restore->original_name);

        return ApiResponse::created($this->restoreRow($restore));
    }

    public function applyRestore(Request $request, string $restore): JsonResponse
    {
        $this->allow('settings.backup.restore');

        $data = $request->validate(['confirm_code' => ['required', 'string']]);
        $row = CompanyRestore::query()->whereKey($restore)->firstOrFail();
        if ($row->status !== 'PREVIEWED') {
            throw ValidationException::withMessages(['confirm_code' => __('settings.system.backup.not_ready')]);
        }
        $code = (string) Company::query()->whereKey($row->company_id)->value('code');
        if (strcasecmp(trim($data['confirm_code']), $code) !== 0) {
            throw ValidationException::withMessages(['confirm_code' => __('settings.system.backup.wrong_code')]);
        }
        if (CompanyRestore::query()->whereIn('status', ['QUEUED', 'RUNNING'])->exists()) {
            throw ValidationException::withMessages(['confirm_code' => __('settings.system.backup.busy')]);
        }
        $row->forceFill(['status' => 'QUEUED'])->save();
        ApplyCompanyRestore::dispatch($row->id, $row->company_id, $this->caller()->user?->id);

        return ApiResponse::ok($this->restoreRow($row->refresh()));
    }

    // ── System Health ───────────────────────────────────────────────────

    public function systemHealth(SystemHealth $health): JsonResponse
    {
        $this->allow('settings.company.manage');

        return ApiResponse::ok($health->cards());
    }

    // ── Data Retention & Purge ──────────────────────────────────────────

    public function retention(RetentionPurger $purger): JsonResponse
    {
        $this->allow('settings.company.manage');

        return ApiResponse::ok($purger->preview($this->caller()->companyId));
    }

    public function saveRetention(Request $request, RetentionPurger $purger): JsonResponse
    {
        $this->allow('settings.company.manage');

        $rules = ['policies' => ['required', 'array']];
        foreach (RetentionPolicy::CATEGORIES as $category => [, $minimum]) {
            $rules["policies.{$category}.keep_days"] = ['sometimes', 'integer', "min:{$minimum}", 'max:36500'];
            $rules["policies.{$category}.active"] = ['sometimes', 'boolean'];
        }
        $data = $request->validate($rules, ['policies.*.keep_days.min' => __('settings.system.retention.too_short', ['min' => ':min'])]);

        $current = collect($purger->policies($this->caller()->companyId))->keyBy('category');
        foreach ($data['policies'] as $category => $policy) {
            if (! isset(RetentionPolicy::CATEGORIES[$category])) {
                continue;
            }
            RetentionPolicy::query()->updateOrCreate(['category' => $category], [
                'company_id' => $this->caller()->companyId,
                'keep_days' => (int) ($policy['keep_days'] ?? $current[$category]['keep_days']),
                'active' => (bool) ($policy['active'] ?? $current[$category]['active']),
            ]);
        }

        return ApiResponse::ok($purger->preview($this->caller()->companyId));
    }

    public function purge(RetentionPurger $purger): JsonResponse
    {
        $this->allow('settings.company.manage');

        $done = $purger->purge($this->caller()->companyId);
        $this->audit->record('RETENTION_PURGED', null, null, $done, companyId: $this->caller()->companyId, label: 'Data Retention & Purge');

        return ApiResponse::ok(['removed' => $done, 'rows' => $purger->preview($this->caller()->companyId)]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * @return list<string>
     */
    private function tenantEvents(): array
    {
        return array_values(array_diff(Notification::EVENT_TYPES, Notification::PLATFORM_EVENT_TYPES));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultRows(): array
    {
        $rows = NotificationEventDefault::query()->get()->keyBy('event_type');

        return collect($this->tenantEvents())
            ->map(function (string $event) use ($rows): array {
                $row = $rows->get($event);

                return [
                    'id' => $event,
                    'event_type' => $event,
                    'in_app' => true,
                    'email' => $row->email ?? NotificationDispatcher::emailsByDefault($event),
                    'sms' => $row->sms ?? false,
                    'whatsapp' => $row->whatsapp ?? false,
                    'locked' => $row->locked ?? false,
                    'customised' => $row !== null,
                    'updated_at' => $row?->updated_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function backupRow(CompanyBackup $b): array
    {
        return [
            'id' => $b->id, 'kind' => $b->kind, 'status' => $b->status, 'size_bytes' => $b->size_bytes, 'checksum' => $b->checksum,
            'tables' => is_array($b->tables_json) ? count($b->tables_json) : null, 'rows' => is_array($b->tables_json) ? array_sum($b->tables_json) : null,
            'requested_by' => $b->requested_by, 'created_at' => $b->created_at?->toIso8601String(), 'finished_at' => $b->finished_at?->toIso8601String(),
            'error' => $b->error, 'downloadable' => $b->status === 'READY' && $b->file_path !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function restoreRow(CompanyRestore $r): array
    {
        $preview = $r->preview_json ?? [];
        // What a restore would write: the audit trail is in the file but never written back.
        $written = array_filter($preview, fn (array $row): bool => $row['restored'] ?? true);

        return [
            'id' => $r->id, 'original_name' => $r->original_name, 'status' => $r->status, 'error' => $r->error,
            'backup_created_at' => $r->manifest_json['created_at'] ?? null,
            'company_code' => $r->manifest_json['company_code'] ?? null,
            'tables' => count($preview),
            'rows' => array_sum(array_column($written, 'in_file')),
            'new' => array_sum(array_column($written, 'new')),
            'existing' => array_sum(array_column($written, 'existing')),
            'preview' => $preview,
            'pre_restore_backup_id' => $r->pre_restore_backup_id,
            'created_at' => $r->created_at?->toIso8601String(), 'applied_at' => $r->applied_at?->toIso8601String(),
        ];
    }
}
