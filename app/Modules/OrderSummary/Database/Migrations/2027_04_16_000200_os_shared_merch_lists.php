<?php

declare(strict_types=1);

use App\Modules\PreBudget\Support\MerchLists;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One list per field (user, 2026-11-23/24): Order Summary's Order Type,
 * Item, Wash Info At Garmenting, Fabric Type, Composition and Sustainability
 * pick from the requisition sheet's lists, so both sheets hold the same
 * words. Each order's value becomes the list's spelling — ignoring case and
 * spaces, then by the user's approved mapping (No Wash → No, Pant → Long
 * pant, Physical BCI+Recycle → Physical BCI + Recycle…); a value the list
 * still lacks (NOS Ready Goods, FORECAST) is added to it. GSM stays as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('os_columns')->whereIn('key', array_keys(MerchLists::OS_COLUMNS))->distinct()->pluck('company_id') as $company) {
            foreach (MerchLists::OS_COLUMNS as $key => $list) {
                DB::table('os_columns')->where('company_id', $company)->where('key', $key)->update(['source' => "pb:{$list}", 'updated_at' => now()]);
            }
            $spelled = []; // "list|value" → the list's spelling, so each distinct value is looked up once
            DB::table('os_orders')->where('company_id', $company)->orderBy('id')->select(['id', 'values'])->chunk(200, function ($rows) use ($company, &$spelled): void {
                foreach ($rows as $row) {
                    $v = json_decode((string) $row->values, true);
                    $changed = false;
                    foreach (MerchLists::OS_COLUMNS as $key => $list) {
                        $old = is_string($v[$key] ?? null) ? trim($v[$key]) : null;
                        if ($old === null || $old === '') {
                            continue;
                        }
                        $new = $spelled["{$list}|{$old}"] ??= MerchLists::ensure($company, $list, $old);
                        if ($new !== $v[$key]) {
                            $v[$key] = $new;
                            $changed = true;
                        }
                    }
                    if ($changed) {
                        DB::table('os_orders')->where('id', $row->id)->update(['values' => json_encode($v, JSON_UNESCAPED_UNICODE)]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // The values keep the shared list's spelling; the columns can be pointed at another list in Column Setup.
    }
};
