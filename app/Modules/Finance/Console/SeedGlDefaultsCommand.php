<?php

declare(strict_types=1);

namespace App\Modules\Finance\Console;

use App\Modules\Finance\Actions\SeedGlDefaults;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Gives an existing company the placeholder chart and mapping (Shared
 * Systems §5.1).
 *
 * New companies get it at onboarding. This is for the ones that predate
 * the GL wiring, and for re-running after finance adds an event — it
 * never touches a mapping that already exists.
 */
class SeedGlDefaultsCommand extends Command
{
    protected $signature = 'finance:seed-gl-defaults {--company= : Only this company id}';

    protected $description = 'Seed placeholder GL accounts and the event mapping for companies that have none';

    public function handle(TenantContext $context, SeedGlDefaults $defaults): int
    {
        $query = Company::withoutGlobalScope(TenantScope::class);

        if (is_string($only = $this->option('company')) && $only !== '') {
            $query->whereKey($only);
        }

        $created = 0;

        foreach ($query->get() as $company) {
            $created += $defaults->forCompany($company->id);
        }

        $context->forget();

        $this->info("Created $created mapping(s).");

        return self::SUCCESS;
    }
}
