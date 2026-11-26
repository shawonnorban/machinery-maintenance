<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\CompanyBackup;
use App\Modules\Settings\Models\RetentionPolicy;
use App\Shared\Files\Models\FileAttachment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Settings → Data Retention & Purge (AnnotechERP 03/04): how much of each kind
 * of the company's data is past its keeping period, and removing it.
 *
 * Every query names the company outright rather than trusting a scope, since
 * the daily command runs outside any request.
 */
class RetentionPurger
{
    /**
     * Each category's policy, its default where the company has none.
     *
     * @return list<array<string, mixed>>
     */
    public function policies(string $companyId): array
    {
        $rows = RetentionPolicy::query()->where('company_id', $companyId)->get()->keyBy('category');

        return collect(RetentionPolicy::CATEGORIES)
            ->map(function (array $limits, string $category) use ($rows): array {
                $row = $rows->get($category);

                return [
                    'id' => $category,
                    'category' => $category,
                    'keep_days' => $row->keep_days ?? $limits[0],
                    'default_days' => $limits[0],
                    'minimum_days' => $limits[1],
                    'active' => $row->active ?? true,
                    'customised' => $row !== null,
                    'last_purged_at' => $row?->last_purged_at?->toIso8601String(),
                    'last_purged_count' => $row?->last_purged_count,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * What a purge would remove now, per category.
     *
     * @return list<array<string, mixed>>
     */
    public function preview(string $companyId): array
    {
        return collect($this->policies($companyId))
            ->map(function (array $policy) use ($companyId): array {
                $cutoff = CarbonImmutable::now()->subDays($policy['keep_days']);

                return $policy + [
                    'cutoff' => $cutoff->toIso8601String(),
                    'due' => $policy['active'] ? $this->query($policy['category'], $companyId, $cutoff)->count() : 0,
                ];
            })
            ->all();
    }

    /**
     * Removes everything past its keeping period for the active policies.
     *
     * @return array<string, int> category => rows removed
     */
    public function purge(string $companyId): array
    {
        $done = [];
        foreach ($this->policies($companyId) as $policy) {
            if (! $policy['active']) {
                continue;
            }
            $cutoff = CarbonImmutable::now()->subDays($policy['keep_days']);
            $count = $this->remove($policy['category'], $companyId, $cutoff);
            $done[$policy['category']] = $count;

            RetentionPolicy::query()->updateOrCreate(
                ['company_id' => $companyId, 'category' => $policy['category']],
                ['keep_days' => $policy['keep_days'], 'active' => true, 'last_purged_at' => CarbonImmutable::now(), 'last_purged_count' => $count],
            );
        }

        return $done;
    }

    private function query(string $category, string $companyId, CarbonImmutable $cutoff): Builder
    {
        $query = match ($category) {
            'NOTIFICATIONS' => DB::table('notifications')->whereNotNull('read_at')->where('created_at', '<', $cutoff),
            'INTEGRATION_LOGS' => DB::table('integration_logs')->where('occurred_at', '<', $cutoff),
            'WEBHOOK_DELIVERIES' => DB::table('webhook_deliveries')->whereIn('status', ['DELIVERED', 'FAILED', 'EXHAUSTED'])->where('created_at', '<', $cutoff),
            'EXPORT_FILES' => DB::table('export_jobs')->whereNotNull('file_id')->where('created_at', '<', $cutoff),
            'BACKUPS' => DB::table('company_backups')->whereIn('status', ['READY', 'FAILED'])->where('created_at', '<', $cutoff),
            'AUDIT_LOGS' => DB::table('audit_logs')->where('created_at', '<', $cutoff),
        };

        return $query->where('company_id', $companyId);
    }

    private function remove(string $category, string $companyId, CarbonImmutable $cutoff): int
    {
        return match ($category) {
            'EXPORT_FILES' => $this->removeExportFiles($companyId, $cutoff),
            'BACKUPS' => $this->expireBackups($companyId, $cutoff),
            // Deliveries go with their notification (cascade).
            default => $this->query($category, $companyId, $cutoff)->delete(),
        };
    }

    /** The files go, along with their file rows; the export row stays to say who asked for what. */
    private function removeExportFiles(string $companyId, CarbonImmutable $cutoff): int
    {
        $count = 0;
        foreach ($this->query('EXPORT_FILES', $companyId, $cutoff)->pluck('file_id', 'id') as $exportId => $fileId) {
            $file = FileAttachment::query()->withoutGlobalScopes()->where('company_id', $companyId)->find($fileId);
            if ($file !== null) {
                Storage::disk($file->disk)->delete($file->path);
                $file->delete();
            }
            DB::table('export_jobs')->where('id', $exportId)->update(['file_id' => null, 'status' => 'EXPIRED']);
            $count++;
        }

        return $count;
    }

    /** The zip goes; the row stays, EXPIRED, as the record that a backup was taken. */
    private function expireBackups(string $companyId, CarbonImmutable $cutoff): int
    {
        $count = 0;
        foreach (CompanyBackup::query()->withoutGlobalScopes()->whereIn('id', $this->query('BACKUPS', $companyId, $cutoff)->pluck('id'))->get() as $backup) {
            if ($backup->file_path) {
                Storage::disk(CompanyBackupWriter::DISK)->delete($backup->file_path);
            }
            $backup->forceFill(['status' => 'EXPIRED', 'file_path' => null])->save();
            $count++;
        }

        return $count;
    }
}
