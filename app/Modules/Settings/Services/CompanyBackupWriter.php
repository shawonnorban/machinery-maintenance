<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\CompanyBackup;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Writes one company's rows to a zip (Settings → Backup & Restore, AnnotechERP
 * 03/04): a JSON-lines file per table, and a manifest naming the company, the
 * schema it came from, and each table's row count and checksum, so a restore
 * can tell a damaged or foreign file before touching anything.
 */
class CompanyBackupWriter
{
    public const FORMAT = 1;

    public const DISK = 'local';

    public function __construct(private readonly TenantTables $tables) {}

    public function write(CompanyBackup $backup): CompanyBackup
    {
        $backup->forceFill(['status' => 'RUNNING', 'started_at' => CarbonImmutable::now(), 'error' => null])->save();
        $work = storage_path('app/tmp/backup-'.$backup->id);
        File::ensureDirectoryExists($work);

        try {
            $company = Company::query()->findOrFail($backup->company_id);
            $counts = [];
            $manifestTables = [];
            foreach ($this->tables->forBackup() as $table) {
                $file = "{$work}/{$table}.jsonl";
                $rows = $this->dump($table, $company->id, $file);
                $counts[$table] = $rows;
                $manifestTables[$table] = ['rows' => $rows, 'sha256' => hash_file('sha256', $file)];
            }

            $manifest = [
                'format' => self::FORMAT,
                'company_id' => $company->id,
                'company_code' => $company->code,
                'created_at' => CarbonImmutable::now()->toIso8601String(),
                'app_version' => (string) config('app.version', ''),
                'migration' => DB::table('migrations')->orderByDesc('id')->value('migration'),
                'tables' => $manifestTables,
            ];
            $manifest['signature'] = self::sign($manifest);

            $path = "backups/{$company->id}/{$backup->id}.zip";
            $this->zip($work, $manifest, Storage::disk(self::DISK)->path($path));

            $backup->forceFill([
                'status' => 'READY',
                'file_path' => $path,
                'size_bytes' => Storage::disk(self::DISK)->size($path),
                'checksum' => hash_file('sha256', Storage::disk(self::DISK)->path($path)),
                'tables_json' => $counts,
                'finished_at' => CarbonImmutable::now(),
            ])->save();
        } catch (Throwable $e) {
            $backup->forceFill(['status' => 'FAILED', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => CarbonImmutable::now()])->save();
            report($e);
        } finally {
            File::deleteDirectory($work);
        }

        return $backup->refresh();
    }

    /**
     * The manifest's signature, keyed with the application key. The manifest
     * carries every table's checksum, so this vouches for the whole file: only
     * this system can produce a backup it will restore, and a hand-made file
     * cannot slip rows in.
     *
     * @param  array<string, mixed>  $manifest
     */
    public static function sign(array $manifest): string
    {
        unset($manifest['signature']);

        return hash_hmac('sha256', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    /** One JSON object per line, streamed so a large table never sits in memory. */
    private function dump(string $table, string $companyId, string $file): int
    {
        $handle = fopen($file, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Cannot write {$file}");
        }
        $count = 0;
        $query = DB::table($table)->where('company_id', $companyId);
        if (Schema::hasColumn($table, 'id')) {
            $query->orderBy('id');
        }
        foreach ($query->cursor() as $row) {
            fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
            $count++;
        }
        fclose($handle);

        return $count;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function zip(string $work, array $manifest, string $target): void
    {
        File::ensureDirectoryExists(dirname($target));
        $zip = new ZipArchive;
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create the backup file.');
        }
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        foreach (array_keys($manifest['tables']) as $table) {
            $zip->addFile("{$work}/{$table}.jsonl", "tables/{$table}.jsonl");
        }
        $zip->close();
    }
}
