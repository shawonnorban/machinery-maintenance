<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\CompanyBackup;
use App\Modules\Settings\Models\CompanyRestore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Brings a backup back (Settings → Backup & Restore, AnnotechERP 03/04), in two
 * steps: inspect — prove the file is this company's, untouched, and fits the
 * schema, and count what it would do — then apply, as a merge.
 *
 * A merge, not a replacement: rows deleted since the backup come back, rows
 * changed since go back to how they were, and rows created since are left
 * alone. Nothing the company entered after the backup is lost by restoring it,
 * and a PRE_RESTORE backup taken first makes the restore itself undoable.
 */
class CompanyRestorer
{
    private const CHUNK = 500;

    public function __construct(
        private readonly TenantTables $tables,
        private readonly CompanyBackupWriter $writer,
    ) {}

    public function inspect(CompanyRestore $restore): CompanyRestore
    {
        try {
            $zip = $this->open($restore);
            $manifest = $this->manifest($zip, $restore->company_id);
            $preview = [];
            $allowed = array_flip($this->tables->forBackup());
            foreach ($manifest['tables'] as $table => $meta) {
                if (! isset($allowed[$table])) {
                    throw new RuntimeException(__('settings.system.backup.unknown_table', ['table' => $table]));
                }
                $preview[] = $this->previewTable($zip, $table, $meta, $restore->company_id);
            }
            $zip->close();

            $restore->forceFill(['status' => 'PREVIEWED', 'manifest_json' => $manifest, 'preview_json' => $preview, 'error' => null])->save();
        } catch (Throwable $e) {
            $restore->forceFill(['status' => 'INVALID', 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
        }

        return $restore->refresh();
    }

    public function apply(CompanyRestore $restore, ?string $userId): CompanyRestore
    {
        $restore->forceFill(['status' => 'RUNNING', 'error' => null])->save();

        try {
            $safety = CompanyBackup::query()->create(['company_id' => $restore->company_id, 'kind' => 'PRE_RESTORE', 'status' => 'QUEUED', 'requested_by' => $userId]);
            if ($this->writer->write($safety)->status !== 'READY') {
                throw new RuntimeException(__('settings.system.backup.safety_failed'));
            }
            $restore->forceFill(['pre_restore_backup_id' => $safety->id])->save();

            $zip = $this->open($restore);
            $manifest = $this->manifest($zip, $restore->company_id);
            DB::transaction(function () use ($zip, $manifest, $restore): int {
                $total = 0;
                foreach ($this->tables->forRestore() as $table) {
                    if (isset($manifest['tables'][$table])) {
                        $total += $this->restoreTable($zip, $table, $restore->company_id);
                    }
                }

                return $total;
            });

            $zip->close();

            $restore->forceFill(['status' => 'APPLIED', 'applied_by' => $userId, 'applied_at' => CarbonImmutable::now()])->save();
        } catch (Throwable $e) {
            $restore->forceFill(['status' => 'FAILED', 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
            report($e);
        }

        return $restore->refresh();
    }

    private function open(CompanyRestore $restore): ZipArchive
    {
        $zip = new ZipArchive;
        if ($zip->open(Storage::disk(CompanyBackupWriter::DISK)->path($restore->file_path), ZipArchive::RDONLY) !== true) {
            throw new RuntimeException(__('settings.system.backup.not_a_backup'));
        }

        return $zip;
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(ZipArchive $zip, string $companyId): array
    {
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (! is_array($manifest) || ($manifest['format'] ?? null) !== CompanyBackupWriter::FORMAT || ! is_array($manifest['tables'] ?? null)) {
            throw new RuntimeException(__('settings.system.backup.not_a_backup'));
        }
        if (! hash_equals(CompanyBackupWriter::sign($manifest), (string) ($manifest['signature'] ?? ''))) {
            throw new RuntimeException(__('settings.system.backup.bad_signature'));
        }
        if (($manifest['company_id'] ?? null) !== $companyId) {
            throw new RuntimeException(__('settings.system.backup.other_company'));
        }

        return $manifest;
    }

    /**
     * @param  array{rows: int, sha256: string}  $meta
     * @return array{table: string, in_file: int, new: int, existing: int, restored: bool}
     */
    private function previewTable(ZipArchive $zip, string $table, array $meta, string $companyId): array
    {
        $columns = array_flip(Schema::getColumnListing($table));
        $hash = hash_init('sha256');
        $rows = 0;
        $existing = 0;
        $ids = [];

        $stream = $this->stream($zip, $table);
        while (($line = fgets($stream)) !== false) {
            hash_update($hash, $line);
            $row = $this->row($line, $table, $companyId);
            foreach (array_keys($row) as $column) {
                if (! isset($columns[$column])) {
                    throw new RuntimeException(__('settings.system.backup.unknown_column', ['table' => $table, 'column' => $column]));
                }
            }
            $rows++;
            $ids[] = $row['id'] ?? null;
            if (count($ids) >= self::CHUNK) {
                $existing += $this->existing($table, $ids, $companyId);
                $ids = [];
            }
        }
        fclose($stream);
        $existing += $this->existing($table, $ids, $companyId);

        if (hash_final($hash) !== $meta['sha256'] || $rows !== (int) $meta['rows']) {
            throw new RuntimeException(__('settings.system.backup.damaged', ['table' => $table]));
        }

        return ['table' => $table, 'in_file' => $rows, 'new' => $rows - $existing, 'existing' => $existing, 'restored' => ! in_array($table, TenantTables::NOT_RESTORED, true)];
    }

    private function restoreTable(ZipArchive $zip, string $table, string $companyId): int
    {
        $columns = Schema::getColumnListing($table);
        $update = array_values(array_diff($columns, ['id', 'company_id']));
        $count = 0;
        $chunk = [];

        $stream = $this->stream($zip, $table);
        while (($line = fgets($stream)) !== false) {
            $chunk[] = array_intersect_key($this->row($line, $table, $companyId), array_flip($columns));
            if (count($chunk) >= self::CHUNK) {
                $count += $this->upsert($table, $chunk, $update, $companyId);
                $chunk = [];
            }
        }
        fclose($stream);

        return $count + $this->upsert($table, $chunk, $update, $companyId);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $update
     */
    private function upsert(string $table, array $rows, array $update, string $companyId): int
    {
        if ($rows === []) {
            return 0;
        }
        // Checked again here, inside the transaction: a row is only ever
        // written over a row of this same company.
        $this->existing($table, array_column($rows, 'id'), $companyId);
        $present = array_keys($rows[0]);

        DB::table($table)->upsert($rows, ['id'], array_values(array_intersect($update, $present)));

        return count($rows);
    }

    /**
     * How many of these ids exist already — and a refusal if any of them belongs
     * to a different company.
     *
     * @param  list<string|null>  $ids
     */
    private function existing(string $table, array $ids, string $companyId): int
    {
        $ids = array_values(array_filter($ids));
        if ($ids === []) {
            return 0;
        }
        $owners = DB::table($table)->whereIn('id', $ids)->pluck('company_id');
        if ($owners->contains(fn ($owner): bool => $owner !== $companyId)) {
            throw new RuntimeException(__('settings.system.backup.other_company'));
        }

        return $owners->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $line, string $table, string $companyId): array
    {
        $row = json_decode($line, true);
        if (! is_array($row) || ($row['company_id'] ?? null) !== $companyId) {
            throw new RuntimeException(__('settings.system.backup.damaged', ['table' => $table]));
        }

        return $row;
    }

    /**
     * @return resource
     */
    private function stream(ZipArchive $zip, string $table)
    {
        $stream = $zip->getStream("tables/{$table}.jsonl");
        if ($stream === false) {
            throw new RuntimeException(__('settings.system.backup.damaged', ['table' => $table]));
        }

        return $stream;
    }
}
