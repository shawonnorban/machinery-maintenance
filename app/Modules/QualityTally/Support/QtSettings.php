<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Quality Tally's own switches (user, 2026-11-28). Read before a tenant is
 * known (a sign-in), so the company is always named.
 */
final class QtSettings
{
    /** A new phone waits for approval before a QC signs in on it. On unless switched off. */
    public static function phoneApproval(string $companyId): bool
    {
        $v = DB::table('qt_settings')->where('company_id', $companyId)->value('phone_approval');

        return $v === null ? true : (bool) $v;
    }

    public static function setPhoneApproval(string $companyId, bool $on, ?string $by): void
    {
        $now = now();
        DB::table('qt_settings')->upsert(
            [['id' => (string) Str::ulid(), 'company_id' => $companyId, 'phone_approval' => $on, 'updated_by' => $by, 'created_at' => $now, 'updated_at' => $now]],
            ['company_id'],
            ['phone_approval', 'updated_by', 'updated_at'],
        );
    }
}
