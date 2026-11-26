<?php

declare(strict_types=1);

namespace App\Modules\Erp\Console;

use App\Modules\Erp\Support\ColumnSetups;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Console\Command;

/**
 * Saves one company's Column Setup — Order Summary, Pre-Budget Requisition and
 * Acc Budget (BOM, with its unit rules) — to the modules' JSON files, so a
 * fresh database starts every new company's sheets from it (user 2026-11-21).
 */
class SaveColumnSetupCommand extends Command
{
    protected $signature = 'erp:save-column-setup {company : Company code (or id) whose Column Setup is saved}';

    protected $description = 'Save a company\'s Order Summary / Requisition / Acc Budget / Dev Sample Column Setup as the setup new companies start from';

    public function handle(): int
    {
        $ref = (string) $this->argument('company');
        $company = Company::query()->where('code', $ref)->orWhere('id', $ref)->first();
        if (! $company) {
            $this->error("No company {$ref}.");

            return self::FAILURE;
        }
        foreach (ColumnSetups::save($company->id) as $name => $data) {
            $path = ColumnSetups::sheets()[$name][0];
            if ($data === null) {
                $this->warn("{$name}: {$company->code} has no Column Setup yet — file left as it is.");

                continue;
            }
            $this->info(sprintf('%s: %d groups, %d columns%s → %s', $name, count($data['groups']), count($data['columns']), isset($data['unit_rules']) ? ', '.count($data['unit_rules']).' unit rules' : '', str_replace(base_path().'/', '', $path)));
        }

        return self::SUCCESS;
    }
}
