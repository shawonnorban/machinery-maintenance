<?php

declare(strict_types=1);

use App\Modules\PreBudget\Support\RequisitionSheet;
use App\Shared\Formula\Formula;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pre-Budget → Requisition (user, 2026-11-19): Fabrication fills itself, as in
 * Norban — Fabric Type, Fabric Composition and GSM, one space between, blanks
 * left out. A company that has not given the column a formula of its own
 * gets this one, and its requisitions are worked out again.
 */
return new class extends Migration
{
    public function up(): void
    {
        $companies = DB::table('pb_columns')->where('key', 'fabrication')->where('is_system', true)->whereNull('formula')->pluck('company_id')->all();
        foreach ($companies as $company) {
            $labels = DB::table('pb_columns')->where('company_id', $company)->pluck('key', 'label');
            $stored = Formula::stored('JOIN(" ", [Fabric Type], [Fabric Composition], [GSM])', fn (string $label) => $labels[$label] ?? null);
            DB::table('pb_columns')->where('company_id', $company)->where('key', 'fabrication')->update(['formula' => $stored, 'updated_at' => now()]);

            $context = app(TenantContext::class);
            $context->forget();
            $context->set($company);
            (new RequisitionSheet($company))->recalculateAll(null);
            $context->forget();
        }
    }

    public function down(): void
    {
        DB::table('pb_columns')->where('key', 'fabrication')->where('is_system', true)->update(['formula' => null]);
    }
};
