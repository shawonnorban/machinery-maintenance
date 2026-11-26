<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Console;

use App\Modules\Tenancy\Models\Company;
use App\Modules\Identity\Models\User;
use App\Modules\OrderSummary\Support\ExcelImport;
use App\Modules\OrderSummary\Support\Sheet;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Order Summary (AnnotechERP 32): read a company's own Excel order summary
 * ("Total Order" sheet) into the sheet. `--replace` first deletes the
 * company's orders and their change log.
 */
final class ImportExcel extends Command
{
    protected $signature = 'ordersummary:import-excel {file : path to the .xlsx} {--company= : company id or code} {--user= : e-mail of who the rows are entered by} {--replace : delete the current orders first} {--pictures= : the book to take the Artwork pictures from (the original .xlsb when reading its .xlsx copy)}';

    protected $description = 'Import the Order Summary Excel (Total Order sheet) into the sheet.';

    public function handle(TenantContext $context): int
    {
        ini_set('memory_limit', '2048M');
        $company = Company::query()->where('id', $this->option('company'))->orWhere('code', $this->option('company'))->first();
        if (! $company || ! is_file($this->argument('file'))) {
            $this->error('Company or file not found.');

            return self::FAILURE;
        }
        $context->forget();
        $context->set($company->id);
        $user = $this->option('user') ? User::query()->where('email', $this->option('user'))->value('id') : null;

        $started = microtime(true);
        $r = (new ExcelImport(new Sheet($company->id), $user))->run($this->argument('file'), (bool) $this->option('replace'), $this->option('pictures') ?: null);
        $context->forget();

        $this->info(sprintf('%s: %d deleted, %d imported, %d with artwork (%d files), %d columns read — %.0fs', $company->code, $r['deleted'], $r['imported'], $r['artworks'], $r['files'], $r['columns'], microtime(true) - $started));
        $this->line('Headers not read: '.(implode(' | ', $r['unmatched']) ?: '—'));
        foreach ($r['list_values_added'] as $label => $n) {
            $this->line("List values added — {$label}: {$n}");
        }
        foreach ($r['not_kept'] as $label => $d) {
            $this->line("Not kept — {$label}: {$d['n']} (".implode(' | ', $d['examples']).')');
        }

        return self::SUCCESS;
    }
}
