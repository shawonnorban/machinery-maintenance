<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Budget Status gains "Budget Cancelled" (a budget cancelled by its order,
 * user 2026-11-24): shown light red, ahead of the other rules, on every
 * company's column that does not colour it yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('os_columns')->where('key', 'budget_status')->get(['id', 'color_rules']) as $c) {
            $rules = json_decode((string) $c->color_rules, true) ?: [];
            if (collect($rules)->contains(fn ($r) => ($r['value'] ?? '') === 'Cancelled')) {
                continue;
            }
            array_unshift($rules, ['op' => 'contains', 'value' => 'Cancelled', 'tone' => 'red']);
            DB::table('os_columns')->where('id', $c->id)->update(['color_rules' => json_encode($rules), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The rule stays; Column Setup changes colours.
    }
};
