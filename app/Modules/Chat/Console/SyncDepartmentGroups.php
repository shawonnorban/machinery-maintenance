<?php

declare(strict_types=1);

namespace App\Modules\Chat\Console;

use App\Modules\Chat\Support\ChatDepartmentGroups;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Keeps one group chat per HR department, its people in step with HR &
 * Payroll (user, 2026-11-23). Hourly, and on demand: `chat:department-groups
 * DAL` for one company (by code), or every company without one.
 */
class SyncDepartmentGroups extends Command
{
    protected $signature = 'chat:department-groups {company? : company code; every company when left out}';

    protected $description = 'Make and update a chat group for each HR department';

    public function handle(): int
    {
        $code = $this->argument('company');
        $companies = DB::table('companies')->when($code, fn ($q) => $q->where('code', $code))->pluck('id', 'code');
        if ($companies->isEmpty()) {
            $this->error("No company {$code}.");

            return self::FAILURE;
        }
        foreach ($companies as $c => $id) {
            ['groups' => $g, 'joined' => $j, 'left' => $l] = ChatDepartmentGroups::sync($id);
            $this->line("{$c}: {$g} department groups, {$j} joined, {$l} left");
        }

        return self::SUCCESS;
    }
}
