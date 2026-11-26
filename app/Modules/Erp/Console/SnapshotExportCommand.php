<?php

declare(strict_types=1);

namespace App\Modules\Erp\Console;

use App\Modules\Erp\Support\DataSnapshot;
use Illuminate\Console\Command;

/**
 * Writes this database's data to database/snapshot, for SnapshotSeeder to
 * load on another server (user 2026-11-26). Run it again whenever the data
 * here changes and should travel.
 */
class SnapshotExportCommand extends Command
{
    protected $signature = 'erp:snapshot-export {--with-audit : Also copy audit_logs (left out by default)}';

    protected $description = 'Copy every business table (companies, users, buyers, styles, orders …) to database/snapshot for SnapshotSeeder';

    public function handle(): int
    {
        $result = DataSnapshot::export((bool) $this->option('with-audit'), fn (string $line) => $this->line($line));

        $this->info(sprintf('%d rows from %d tables → %s', $result['rows'], $result['tables'], DataSnapshot::DIR));
        $this->line('Load it elsewhere with: php artisan db:seed --class="Database\Seeders\SnapshotSeeder" --force');

        return self::SUCCESS;
    }
}
