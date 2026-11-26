<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Order Summary's Budget Status (user, 2026-11-24): a column after Remarks
 * showing where each order stands in Pre-Budget (Requisition, Budget Draft,
 * Submitted, Returned, Approved) — worked out from the requisition and its
 * budget each time (OrderStatus), so a system column: nothing typed or
 * stored. Added to every company's sheet that has one.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('os_columns')->where('key', 'remarks')->get(['company_id', 'group_id', 'sort_order']) as $remarks) {
            if (DB::table('os_columns')->where('company_id', $remarks->company_id)->where('key', 'budget_status')->exists()) {
                continue;
            }
            // Right after Remarks: the columns behind it in its group move one place on.
            DB::table('os_columns')->where('company_id', $remarks->company_id)->where('group_id', $remarks->group_id)->where('sort_order', '>', $remarks->sort_order)->increment('sort_order', 10);
            $label = DB::table('os_columns')->where('company_id', $remarks->company_id)->where('label', 'Budget Status')->exists() ? 'Pre-Budget Status' : 'Budget Status';
            DB::table('os_columns')->insert([
                'id' => Str::lower((string) Str::ulid()), 'company_id' => $remarks->company_id, 'group_id' => $remarks->group_id, 'key' => 'budget_status', 'label' => $label,
                'sort_order' => $remarks->sort_order + 10, 'type' => 'system', 'width' => 130, 'is_system' => true,
                'color_rules' => json_encode([
                    ['op' => 'contains', 'value' => 'Approved', 'tone' => 'green'], ['op' => 'contains', 'value' => 'Submitted', 'tone' => 'yellow'],
                    ['op' => 'contains', 'value' => 'Returned', 'tone' => 'lightred'], ['op' => 'contains', 'value' => 'Draft', 'tone' => 'blue'],
                    ['op' => 'contains', 'value' => 'Requisition', 'tone' => 'gray'],
                ]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('os_columns')->where('key', 'budget_status')->delete();
    }
};
