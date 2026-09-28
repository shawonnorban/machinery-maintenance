<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\GlEventMapping;
use Illuminate\Support\Facades\DB;

/**
 * A placeholder chart, and every event mapped onto it (Shared Systems §5.1).
 *
 * The spec's instruction is to start with suspense accounts rather than
 * wait for finance: "শুরুতে placeholder/Suspense account দিয়ে সিড করে রাখা
 * যায়। Finance sign-off দিলে শুধু এই ম্যাপিং টেবিলের ডাটা বদলাবে". That is
 * what this does, and it is why the accounts are named after what they
 * hold rather than numbered — a real chart will renumber them, and nothing
 * in the code reads a code.
 *
 * Idempotent: an account or mapping that already exists is left exactly as
 * it is, so re-running this never undoes a finance team's work.
 */
class SeedGlDefaults
{
    /**
     * Placeholder accounts, keyed by the slot the mapping refers to.
     *
     * @var array<string, array{code: string, name: string, category: string}>
     */
    public const ACCOUNTS = [
        'STOCK' => ['code' => 'SUS-STOCK', 'name' => 'Stock (suspense)', 'category' => 'ASSET'],
        'GRN_CLEARING' => ['code' => 'SUS-GRNCLR', 'name' => 'GRN clearing (suspense)', 'category' => 'LIABILITY'],
        'WIP' => ['code' => 'SUS-WIP', 'name' => 'Work in progress (suspense)', 'category' => 'ASSET'],
        'VENDOR_PAYABLE' => ['code' => 'SUS-AP', 'name' => 'Vendor payable (suspense)', 'category' => 'LIABILITY'],
        'ADVANCE_TO_SUPPLIER' => ['code' => 'SUS-ADV', 'name' => 'Advance to supplier (suspense)', 'category' => 'ASSET'],
        'BANK' => ['code' => 'SUS-BANK', 'name' => 'Bank (suspense)', 'category' => 'ASSET'],
        'LANDED_COST_PAYABLE' => ['code' => 'SUS-LCPAY', 'name' => 'Landed cost payable (suspense)', 'category' => 'LIABILITY'],
        'STOCK_VARIANCE' => ['code' => 'SUS-VAR', 'name' => 'Stock count variance (suspense)', 'category' => 'EXPENSE'],
    ];

    /**
     * @return int how many mappings were created
     */
    public function forCompany(string $companyId): int
    {
        return DB::transaction(function () use ($companyId): int {
            $accounts = [];

            foreach (self::ACCOUNTS as $slot => $spec) {
                $accounts[$slot] = GlAccount::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('code', $spec['code'])
                    ->first()
                    ?? GlAccount::withoutGlobalScopes()->create([
                        'company_id' => $companyId,
                        'code' => $spec['code'],
                        'name' => $spec['name'],
                        'account_category' => $spec['category'],
                        'active' => true,
                    ]);
            }

            $created = 0;

            foreach (GlEventMapping::EVENTS as $event => $slots) {
                $exists = GlEventMapping::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('event_type', $event)
                    ->exists();

                if ($exists) {
                    continue;
                }

                GlEventMapping::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'event_type' => $event,
                    'debit_account_id' => $accounts[$slots['debit']]->id,
                    'credit_account_id' => $accounts[$slots['credit']]->id,
                    'secondary_credit_account_id' => isset($slots['secondary_credit'])
                        ? $accounts[$slots['secondary_credit']]->id
                        : null,
                    'active' => true,
                    'notes' => 'Placeholder mapping (Shared Systems §5.1). Point these at the real chart once finance signs off.',
                ]);

                $created++;
            }

            return $created;
        });
    }
}
