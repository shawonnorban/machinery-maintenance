<?php

declare(strict_types=1);

namespace App\Modules\Erp\Support;

use App\Modules\Api\Support\ApiCaller;
use App\Modules\DevSample\Support\SampleNotices;
use App\Modules\ExportStatement\Support\EsNotices;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\OrderSummary\Support\OrderNotices;
use App\Modules\PreBudget\Support\BudgetNotices;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifications from the sheet modules — Order Summary, Dev Sample Status and
 * Pre-Budget (user, 2026-11-21), and Export Statement (2026-11-22). Every save there is logged as one batch of
 * changes; the batch is noted here and, once the response has gone, each
 * person who should hear of it gets one notification for the whole save —
 * never one per cell. The one who saved is never told of their own save.
 *
 * Only a person's save through the app counts: an Excel import or another
 * console job writes the same log but nobody is told of it. A failure is
 * logged and never breaks the save it announces.
 */
final class SheetNotices
{
    /** @var array<string, array{0: string, 1: string}> batch => [module, company] */
    private static array $pending = [];

    private static bool $hooked = false;

    /** A saved batch of changes in `$module` (os, ds, pb, es), told once the request ends. */
    public static function note(string $module, string $batch): void
    {
        $company = app(TenantContext::class)->companyIdOrNull();
        if ($company === null || ! config('app.sheet_notices', true) || rescue(fn () => app(ApiCaller::class), null, false) === null) {
            return;
        }
        self::$pending[$batch] = [$module, $company];
        if (! self::$hooked) {
            self::$hooked = true;
            app()->terminating(fn () => self::flush());
        }
    }

    /** Tell every noted batch (also called directly by a caller that cannot wait for the request to end). */
    public static function flush(): void
    {
        $todo = self::$pending;
        self::$pending = [];
        self::$hooked = false;
        $context = app(TenantContext::class);
        foreach ($todo as $batch => [$module, $company]) {
            self::guard(function () use ($context, $company, $module, $batch): void {
                if ($context->companyIdOrNull() !== $company) {
                    $context->set($company);
                }
                match ($module) {
                    'os' => OrderNotices::batch($batch),
                    'ds' => SampleNotices::batch($batch),
                    'pb' => BudgetNotices::batch($batch),
                    'es' => EsNotices::batch($batch),
                };
            });
            if ($module === 'os') {
                // JOB No / PO Number / Shipment ID added or changed: mailed at once (user, 2026-11-22).
                self::guard(fn () => \App\Modules\OrderSummary\Support\OrderKeyMail::batch($batch));
            }
        }
    }

    /**
     * One notification to each of `$userIds` (but `$except`), the data made in
     * the recipient's own language: `$data(string $locale, string $userId)`.
     *
     * @param  iterable<string>  $userIds
     * @param  callable(string, string): ?array<string, mixed>  $data  null skips that person
     */
    public static function send(iterable $userIds, string $event, callable $data, ?string $url, string $severity = 'INFO', ?string $except = null, ?string $entityType = null, ?string $entityId = null): int
    {
        $ids = collect($userIds)->filter()->unique()->reject(fn ($id) => $id === $except)->values();
        if ($ids->isEmpty()) {
            return 0;
        }
        $dispatcher = app(NotificationDispatcher::class);
        $n = 0;
        foreach (User::query()->whereKey($ids)->where('status', 'ACTIVE')->get() as $user) {
            $payload = $data($user->locale ?? config('app.locale'), $user->id);
            if ($payload === null) {
                continue;
            }
            self::guard(function () use ($dispatcher, $user, $event, $payload, $severity, $entityType, $entityId, $url, &$n): void {
                $dispatcher->send($user, $event, $payload, $severity, null, $entityType, $entityId, $url) && $n++;
            });
        }

        return $n;
    }

    /**
     * The company's active people whose roles carry `$permission`.
     *
     * @return Collection<int, string> user ids
     */
    public static function holders(string $permission): Collection
    {
        $company = app(TenantContext::class)->companyId();
        $roles = DB::table('role_has_permissions')->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', $permission)->pluck('role_has_permissions.role_id')->all();
        if ($roles === []) {
            return collect();
        }

        return DB::table('user_roles')->join('company_users', fn ($j) => $j->on('company_users.user_id', '=', 'user_roles.user_id')->where('company_users.company_id', $company))
            ->where('user_roles.company_id', $company)->where('company_users.status', 'ACTIVE')->whereIn('user_roles.role_id', $roles)
            ->distinct()->pluck('user_roles.user_id');
    }

    /** A user's name, for "who saved". */
    public static function name(?string $userId): string
    {
        return $userId ? (string) (DB::table('users')->where('id', $userId)->value('name') ?? '—') : '—';
    }

    /** Up to three names, then "+N". @param  iterable<string>  $names */
    public static function list(iterable $names): string
    {
        $all = collect($names)->filter(fn ($n) => $n !== null && $n !== '')->unique()->values();

        return $all->take(3)->implode(', ').($all->count() > 3 ? ' +'.($all->count() - 3) : '');
    }

    private static function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning('Sheet notification failed', ['error' => $e->getMessage()]);
        }
    }
}
