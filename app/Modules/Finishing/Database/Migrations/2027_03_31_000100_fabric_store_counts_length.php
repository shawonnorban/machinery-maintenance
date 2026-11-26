<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Fabric by length (the user's decision of 2026-11-16), step 1: the finished
 * fabric store and the cutting floor. Each delivery line, lot, roll, move,
 * requisition line and return carries its length in metres beside its kilos
 * (shown in the company's yards or metres); the cutting's lay and output too.
 * Null where a row was made before lengths were kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_store_delivery_lines', fn (Blueprint $t) => $t->decimal('finish_m', 14, 3)->nullable()->after('finish_qty'));
        Schema::table('fin_store_lots', fn (Blueprint $t) => $t->decimal('received_m', 14, 3)->nullable()->after('received_kg'));
        Schema::table('fin_store_rolls', fn (Blueprint $t) => $t->decimal('length_m', 12, 3)->nullable()->after('weight_kg'));
        Schema::table('fin_store_moves', fn (Blueprint $t) => $t->decimal('qty_m', 14, 3)->nullable()->after('qty_kg'));
        Schema::table('fin_cut_requisition_lines', function (Blueprint $t): void {
            $t->decimal('req_m', 14, 3)->nullable()->after('req_qty');
            $t->decimal('issue_m', 14, 3)->nullable()->after('issue_qty');
        });
        Schema::table('fin_store_returns', fn (Blueprint $t) => $t->decimal('qty_m', 14, 3)->nullable()->after('qty_kg'));
        Schema::table('cut_inputs', function (Blueprint $t): void {
            $t->decimal('lay_m', 14, 3)->nullable()->after('plies'); // marker × plies, or as measured
            $t->decimal('used_m', 14, 3)->nullable()->after('used_kg');
            $t->decimal('remnant_m', 14, 3)->nullable()->after('remnant_kg');
            // The kilos follow the length now — unknown when the fabric's GSM and width are not given.
            $t->decimal('fabric_kg', 12, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cut_inputs', fn (Blueprint $t) => $t->dropColumn(['lay_m', 'used_m', 'remnant_m']));
        Schema::table('fin_store_returns', fn (Blueprint $t) => $t->dropColumn('qty_m'));
        Schema::table('fin_cut_requisition_lines', fn (Blueprint $t) => $t->dropColumn(['req_m', 'issue_m']));
        Schema::table('fin_store_moves', fn (Blueprint $t) => $t->dropColumn('qty_m'));
        Schema::table('fin_store_rolls', fn (Blueprint $t) => $t->dropColumn('length_m'));
        Schema::table('fin_store_lots', fn (Blueprint $t) => $t->dropColumn('received_m'));
        Schema::table('fin_store_delivery_lines', fn (Blueprint $t) => $t->dropColumn('finish_m'));
    }
};
