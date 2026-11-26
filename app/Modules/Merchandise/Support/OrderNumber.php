<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Support;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/** The order book's numbers (AnnotechERP 06/07): LPP-2026-000001 — the buyer's code, the year and the buyer's next number that year. */
final class OrderNumber
{
    /** Call inside the save's transaction: the lock is held to its end, so two clerks never take one number. */
    public static function next(string $buyerId): string
    {
        $code = strtoupper((string) (Buyer::query()->whereKey($buyerId)->value('code') ?: 'ORD'));
        $prefix = $code.'-'.now()->format('Y').'-';
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_orders:'.app(TenantContext::class)->companyId().':'.$prefix]);
        // A number typed by hand in the same series counts; one in another shape does not.
        $n = 1 + (int) Order::query()->where('order_no', 'like', $prefix.'%')->pluck('order_no')
            ->map(fn (string $no): string => substr($no, strlen($prefix)))->filter(fn (string $tail): bool => ctype_digit($tail))->map(fn (string $tail): int => (int) $tail)->max();

        return $prefix.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
