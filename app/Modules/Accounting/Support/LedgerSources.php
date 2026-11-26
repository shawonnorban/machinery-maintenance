<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The GL sources the system posts through (Link Ref => names), offered on
 * Ledger Integration Setup from the first visit. Each needs its COA lines
 * (a debit and a credit ledger) before it posts anything.
 */
final class LedgerSources
{
    /** Machinery & Maintenance's costs, by kind. */
    public const MACHINERY = [
        'MACHINERY_PARTS' => ['Machinery — Spare Parts Used', 'মেশিনারি — ব্যবহৃত স্পেয়ার পার্টস'],
        'MACHINERY_LABOUR' => ['Machinery — Maintenance Labour', 'মেশিনারি — রক্ষণাবেক্ষণ শ্রম'],
        'MACHINERY_VENDOR' => ['Machinery — Vendor & External Service', 'মেশিনারি — ভেন্ডর ও বাইরের সার্ভিস'],
        'MACHINERY_OTHER' => ['Machinery — Other Costs', 'মেশিনারি — অন্যান্য খরচ'],
    ];

    /** A Machinery cost's kind (its source type) => the GL source it posts through. */
    public const COST_SOURCE = [
        'PARTS' => 'MACHINERY_PARTS',
        'LABOR' => 'MACHINERY_LABOUR',
        'VENDOR' => 'MACHINERY_VENDOR',
        'EXTERNAL_SERVICE' => 'MACHINERY_VENDOR',
        'TRANSPORT' => 'MACHINERY_OTHER',
        'MANUAL' => 'MACHINERY_OTHER',
    ];

    public static function ensure(string $companyId): void
    {
        foreach (self::MACHINERY as $ref => [$name, $nameBn]) {
            $exists = DB::table('acc_gl_sources')->where('company_id', $companyId)->where('link_ref', $ref)->exists();
            if (! $exists && ! DB::table('acc_gl_sources')->where('company_id', $companyId)->where('name', $name)->exists()) {
                DB::table('acc_gl_sources')->insert([
                    'id' => (string) Str::ulid(), 'company_id' => $companyId, 'name' => $name, 'name_bn' => $nameBn, 'mapping_type' => 'GENERAL',
                    'link_ref' => $ref, 'final_bill_collection' => false, 'editable_from_gl' => false, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }
}
