<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Fabric Requirement → Master Data, matched again to Brickland (AnnotechERP
 * 13/01): a standard batch hour and a machine routing are each set for a
 * colour group — Dye House's colour groups, the register both read.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['fp_batch_hours', 'fp_machine_routes'] as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->foreignUlid('colour_group_id')->nullable()->after('company_id')->constrained('dye_colour_groups')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['fp_batch_hours', 'fp_machine_routes'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('colour_group_id'));
        }
    }
};
