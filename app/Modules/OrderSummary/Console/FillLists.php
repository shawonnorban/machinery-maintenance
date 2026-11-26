<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Console;

use App\Modules\Identity\Models\User;
use App\Modules\OrderSummary\Models\OsOrder;
use App\Modules\OrderSummary\Support\ListFiller;
use App\Modules\OrderSummary\Support\Sheet;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Order Summary (AnnotechERP 32): values already in the sheet that their
 * column's list lacks (from an import, or a list value since deleted) added
 * to the list — lookup lists and Supplier Master (ListFiller). `--column`
 * limits it to some columns.
 */
final class FillLists extends Command
{
    protected $signature = 'ordersummary:fill-lists {--company= : company id or code} {--user= : e-mail of who adds the values} {--column=* : column keys (default: every dropdown / list column)}';

    protected $description = 'Add the Order Summary values a dropdown list lacks to that list (lookup lists, Supplier Master).';

    public function handle(TenantContext $context): int
    {
        $company = Company::query()->where('id', $this->option('company'))->orWhere('code', $this->option('company'))->first();
        if (! $company) {
            $this->error('Company not found.');

            return self::FAILURE;
        }
        $context->forget();
        $context->set($company->id);
        $user = $this->option('user') ? User::query()->where('email', $this->option('user'))->value('id') : null;
        $sheet = new Sheet($company->id);
        $filler = new ListFiller($sheet, $user);
        $only = (array) $this->option('column');
        $columns = $sheet->columns->filter(fn ($c) => in_array($c->type, ['dropdown', 'multi'], true) && $c->source && ($only === [] || in_array($c->key, $only, true)));

        OsOrder::query()->select(['id', 'values'])->orderBy('serial_no')->chunk(500, function ($orders) use ($columns, $filler): void {
            foreach ($orders as $o) {
                foreach ($columns as $c) {
                    foreach ((array) ($o->values[$c->key] ?? []) as $v) {
                        if (is_string($v) && trim($v) !== '') {
                            $filler->keep($c, $v);
                        }
                    }
                }
            }
        });
        $context->forget();

        foreach ($filler->added as $label => $n) {
            $this->line("Added — {$label}: {$n}");
        }
        $this->info($company->code.': '.array_sum($filler->added).' list values added.');

        return self::SUCCESS;
    }
}
