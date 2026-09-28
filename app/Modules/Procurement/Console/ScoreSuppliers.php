<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Console;

use App\Modules\Procurement\Actions\CalculateSupplierScorecard;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Monthly supplier scorecards (Procurement spec §15).
 *
 * Scored over a **rolling window** rather than a single calendar month:
 * a mill that delivers twice a quarter would otherwise have a card built
 * from one receipt, and one late delivery would read as 0% on time. Three
 * months is long enough for the numbers to mean something and short
 * enough to still describe the supplier the factory has now.
 *
 * The period is re-scored each run rather than appended to, because the
 * figures behind it keep moving — a claim settled in November changes what
 * October looked like — and a card is only useful if it says what is true
 * when somebody opens it.
 */
class ScoreSuppliers extends Command
{
    protected $signature = 'procurement:score-suppliers
        {--months=3 : How many months back the window reaches}
        {--to= : End the window on this date instead of today}';

    protected $description = 'Score every supplier on delivery, quality, price and claims';

    public function handle(TenantContext $context, CalculateSupplierScorecard $scorecards): int
    {
        $months = max(1, (int) $this->option('months'));
        $to = CarbonImmutable::parse((string) ($this->option('to') ?: CarbonImmutable::today()));
        $from = $to->subMonths($months)->addDay();

        $companies = Company::withoutGlobalScope(TenantScope::class)->get();
        $scored = 0;

        foreach ($companies as $company) {
            $context->forget();
            $context->set($company->id);

            $result = $scorecards->forAllVendors($from, $to);
            $scored += $result['cards'];
        }

        $context->forget();

        $this->info("Scored $scored supplier(s) over {$from->toDateString()} → {$to->toDateString()}.");

        return self::SUCCESS;
    }
}
