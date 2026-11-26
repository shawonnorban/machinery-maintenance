<?php

declare(strict_types=1);

use App\Modules\Inventory\Support\UomCodes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The screens that keep a unit as its code (trims, bookings, budgets,
 * challans, the material masters …) now pick it from the units-of-measure
 * master. What was typed before is brought to the master's code: "Pcs" →
 * PCS, "Kgs" → KG, "yards" → YDS. Measure points keep a measurement unit,
 * CM or INCH. A value that names no unit is left as it was, for a person to
 * fix on the screen.
 */
return new class extends Migration
{
    private const TABLES = [
        'buyer_trim_items', 'dye_sc_quotation_lines', 'merch_acc_booking_lines', 'merch_acc_mrr_lines', 'merch_acc_po_lines', 'merch_acc_requisition_lines',
        'merch_acc_return_lines', 'merch_budget_lines', 'merch_cad_sizes', 'merch_fabric_booking_lines', 'merch_ffab_ob_lines', 'merch_pfab_indent_lines',
        'merch_pfab_ledger', 'merch_pfab_mrr_lines', 'merch_pfab_ob_lines', 'merch_pfab_po_lines', 'merch_pfab_return_lines', 'merch_sample_challan_items',
        'merch_trim_dyeing_items', 'mp_items', 'rate_lines',
    ];

    private const MEASURE = ['CM' => 'CM', 'CMS' => 'CM', 'INCH' => 'INCH', 'INCHES' => 'INCH', 'IN' => 'INCH', '"' => 'INCH'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->recode($table, fn (string $value) => UomCodes::normalize($value));
        }
        $this->recode('merch_measure_points', fn (string $value) => self::MEASURE[mb_strtoupper(trim($value))] ?? null);
    }

    public function down(): void
    {
        // The old spellings are not kept; the codes stand.
    }

    private function recode(string $table, Closure $code): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uom')) {
            return;
        }
        foreach (DB::table($table)->whereNotNull('uom')->distinct()->pluck('uom') as $value) {
            $new = $code((string) $value);
            if ($new !== null && $new !== $value) {
                DB::table($table)->where('uom', $value)->update(['uom' => $new]);
            }
        }
    }
};
