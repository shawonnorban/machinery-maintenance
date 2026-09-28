<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a roll became (Inventory spec §5, §6) and whether its price was
 * corrected afterwards (§10.5).
 *
 * `parent_roll_id` already existed for splits — a child points at the roll
 * it was cut from. A merge needs the opposite direction: several rolls
 * point at the one they were folded into, which `parent_roll_id` cannot
 * express without reading backwards.
 *
 * `cost_adjusted_after_issue` is the flag §10.5 asks for. Landed cost
 * arrives weeks after the cloth does, so a roll that has already been
 * issued still gets its real price — and the report has to be able to say
 * that the figure it is showing now is not the one it showed last month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_rolls', function (Blueprint $table): void {
            $table->ulid('merged_into_roll_id')->nullable()->after('parent_roll_id');
            $table->boolean('cost_adjusted_after_issue')->default(false)->after('currency');
        });

        // Self-reference added separately, as `parent_roll_id`'s is.
        Schema::table('fabric_rolls', function (Blueprint $table): void {
            $table->foreign('merged_into_roll_id', 'fabric_rolls_merged_into_fk')
                ->references('id')->on('fabric_rolls')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fabric_rolls', function (Blueprint $table): void {
            $table->dropForeign('fabric_rolls_merged_into_fk');
            $table->dropColumn(['merged_into_roll_id', 'cost_adjusted_after_issue']);
        });
    }
};
