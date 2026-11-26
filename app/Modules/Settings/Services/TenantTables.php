<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use Illuminate\Support\Facades\Schema;

/**
 * Which tables hold a company's own data, and in what order they can be
 * written back (Settings → Backup & Restore, AnnotechERP 03/04).
 *
 * Found from the schema rather than listed by hand — every table with a
 * company_id — so a module added next year is backed up without anybody
 * remembering to add it. What is left out is listed instead.
 */
class TenantTables
{
    /**
     * Never written to a backup file:
     * - secrets and sign-in state (tokens, API clients, idempotency keys);
     * - people and their memberships, which outlive one company's restore;
     * - billing and platform support, which the platform owns, not the company;
     * - the backup machinery itself.
     */
    public const NEVER = [
        'api_clients', 'api_tokens', 'personal_access_tokens', 'idempotency_keys',
        'company_users', 'user_roles', 'model_has_roles', 'model_has_permissions', 'legacy_roles',
        'subscription_contracts', 'subscription_invoices', 'subscription_invoice_lines', 'subscription_payments',
        'credit_notes', 'refunds', 'usage_metrics', 'company_domains',
        'support_grants', 'support_tickets', 'support_ticket_messages',
        'company_backups', 'company_restores',
    ];

    /** In the backup, but never written back: the audit trail only ever grows. */
    public const NOT_RESTORED = ['audit_logs'];

    /**
     * Every company-owned table that goes into a backup, parents before children.
     *
     * @return list<string>
     */
    public function forBackup(): array
    {
        $tables = collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn (string $t): bool => in_array($t, self::NEVER, true))
            ->filter(fn (string $t): bool => Schema::hasColumn($t, 'company_id'))
            ->values()
            ->all();

        return $this->ordered($tables);
    }

    /**
     * @return list<string>
     */
    public function forRestore(): array
    {
        return array_values(array_diff($this->forBackup(), self::NOT_RESTORED));
    }

    /**
     * Parents before children, so a row is never written before the row it
     * points at. A cycle (two tables pointing at each other) keeps its tables in
     * name order at the end; the restore's single transaction then reports it
     * rather than leaving half a company behind.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function ordered(array $tables): array
    {
        sort($tables);
        $set = array_flip($tables);
        $parents = [];
        foreach ($tables as $table) {
            $parents[$table] = collect(Schema::getForeignKeys($table))
                ->pluck('foreign_table')
                ->filter(fn (string $p): bool => isset($set[$p]) && $p !== $table)
                ->unique()
                ->values()
                ->all();
        }

        $out = [];
        $done = [];
        $progress = true;
        while ($progress && count($out) < count($tables)) {
            $progress = false;
            foreach ($tables as $table) {
                if (isset($done[$table])) {
                    continue;
                }
                if (array_diff($parents[$table], array_keys($done)) === []) {
                    $out[] = $table;
                    $done[$table] = true;
                    $progress = true;
                }
            }
        }

        foreach ($tables as $table) {
            if (! isset($done[$table])) {
                $out[] = $table;
            }
        }

        return $out;
    }
}
