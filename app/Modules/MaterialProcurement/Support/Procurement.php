<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Support;

use App\Modules\Api\Support\ApiCaller;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\AuditEntry;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What every Material Procurement document shares: its number, counted per
 * company and year (PR-2026-00001), the people named on it, and the entry
 * each critical transaction leaves in the module's audit trail.
 */
final class Procurement
{
    /** The next number of a document; taken under a lock, so two saves never share one. */
    public static function nextNumber(string $prefix, string $table, string $column, string $date): string
    {
        $company = app(TenantContext::class)->companyId();
        $head = $prefix.'-'.CarbonImmutable::parse($date)->format('Y').'-';
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ["{$table}:{$head}:{$company}"]);

        return $head.str_pad((string) (1 + self::last($table, $column, $head)), 5, '0', STR_PAD_LEFT);
    }

    /** What the next number would be, shown before the first save ("PR-2026-00006 (on save)"). */
    public static function previewNumber(string $prefix, string $table, string $column, string $date): string
    {
        $head = $prefix.'-'.CarbonImmutable::parse($date)->format('Y').'-';

        return $head.str_pad((string) (1 + self::last($table, $column, $head)), 5, '0', STR_PAD_LEFT);
    }

    /** @return array<string, string> user id => name */
    public static function names(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids ? DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->all() : [];
    }

    /**
     * The company's active people whose roles carry a permission — who
     * reviews prices, who may be handed a material. A supplier's portal login
     * never carries these.
     *
     * @return Collection<int, User>
     */
    public static function usersWith(string $permission): Collection
    {
        $company = app(TenantContext::class)->companyId();
        $roles = DB::table('role_has_permissions')->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', $permission)->pluck('role_has_permissions.role_id')->all();

        return User::query()->join('company_users', 'company_users.user_id', '=', 'users.id')
            ->where('company_users.company_id', $company)->where('company_users.status', 'ACTIVE')
            ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('user_roles')->whereColumn('user_roles.user_id', 'users.id')
                ->where('user_roles.company_id', $company)->whereIn('user_roles.role_id', $roles ?: [0]))
            ->orderBy('users.name')->get(['users.*']);
    }

    /**
     * An entry in the module's audit trail, by the caller. Outside a request
     * (the sample masters seeded from the console) there is nobody to
     * record, so nothing is written.
     */
    public static function audit(string $action, string $screen, ?string $reference, string $summary): void
    {
        $caller = rescue(fn (): ApiCaller => app(ApiCaller::class), null, false);
        if (! $caller) {
            return;
        }
        AuditEntry::query()->create([
            'user_id' => $caller->auditUserId(), 'action' => $action, 'screen' => $screen,
            'reference' => $reference, 'summary' => mb_substr($summary, 0, 2000), 'created_at' => now(),
        ]);
    }

    /** An audit trail summary: always in English, as Brickland keeps it, whatever language the user works in. */
    public static function en(string $key, array $params = []): string
    {
        return __($key, $params, 'en');
    }

    /** A quantity as people write it: 40, 2.5, 0.125 — never 40.000. */
    public static function qty(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    }

    private static function last(string $table, string $column, string $head): int
    {
        return (int) DB::table($table)->where('company_id', app(TenantContext::class)->companyId())->where($column, 'like', $head.'%')->pluck($column)
            ->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();
    }
}
