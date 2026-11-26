<?php

declare(strict_types=1);

namespace App\Modules\Erp\Console;

use Database\Seeders\ErpMasterDataSeeder;
use Illuminate\Console\Command;

/** Seeds the sample master data of every built module into each company (or the ones named); safe to run again. */
class SeedMastersCommand extends Command
{
    protected $signature = 'erp:seed-masters {companies?* : Company codes; every company when left out}';

    protected $description = 'Seed sample master data (buyers, colours, suppliers, machines …) through each screen\'s own rules';

    public function handle(): int
    {
        $seeder = new ErpMasterDataSeeder;
        $seeder->companies = $this->argument('companies');
        $seeder->run();

        foreach ($seeder->report as $code => $created) {
            $total = array_sum($created);
            $this->info("{$code}: {$total} rows created");
            foreach ($created as $key => $count) {
                $this->line("  {$key}: {$count}");
            }
            // The same reason row after row (every route of a missing machine) prints once, counted.
            foreach (array_count_values($seeder->skipped[$code] ?? []) as $reason => $times) {
                $this->warn('  skipped '.($times > 1 ? "(×{$times}) " : '').$reason);
            }
        }

        return self::SUCCESS;
    }
}
