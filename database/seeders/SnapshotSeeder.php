<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Erp\Support\DataSnapshot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Loads database/snapshot (made by `php artisan erp:snapshot-export`) into
 * this database: companies, factories, buyers, users, employees, brands,
 * seasons, departments, styles, orders — every table the snapshot holds.
 *
 * REPLACES the data: each snapshot table is emptied first. It is therefore
 * never part of DatabaseSeeder and only runs when named:
 *
 *   php artisan migrate --force
 *   php artisan db:seed --class="Database\Seeders\SnapshotSeeder" --force
 *
 * Uploaded files (storage/app) are not in the database; copy them separately.
 */
class SnapshotSeeder extends Seeder
{
    public function run(): void
    {
        $result = DataSnapshot::import(
            fn (string $line) => $this->command?->line($line),
            fn (string $line) => $this->command?->warn($line),
        );

        // Permission, menu and setting caches still hold the old rows.
        Cache::flush();

        $this->command?->info(sprintf('Snapshot loaded: %d rows into %d tables.', $result['rows'], $result['tables']));
    }
}
