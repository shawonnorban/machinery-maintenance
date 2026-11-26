<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A readable number for the Machinery records whose pages still had a ULID
 * in the address (AnnotechERP: no internal ids in URLs): a maintenance plan
 * (PM-00001), a warranty (WTY-00001) and a meter on a machine (MTR-00001).
 * Names repeat across machines, so they cannot stand in for one.
 *
 * Existing rows are numbered in the order they were made, per company, and
 * each company's counter is left at its last number so the next record
 * created through NumberSequenceGenerator carries on from there.
 */
return new class extends Migration
{
    /** table => [column, series, prefix] — the formats NumberSequenceGenerator::FORMATS gives these series. */
    private const NUMBERED = [
        'maintenance_plans' => ['plan_number', 'MAINTENANCE_PLAN', 'PM-'],
        'warranties' => ['warranty_number', 'WARRANTY', 'WTY-'],
        'asset_meters' => ['meter_number', 'ASSET_METER', 'MTR-'],
    ];

    public function up(): void
    {
        foreach (self::NUMBERED as $table => [$column, $series, $prefix]) {
            Schema::table($table, function (Blueprint $t) use ($column): void {
                // Nullable only so the column can be added to rows that exist;
                // every row is numbered just below, and every new one on create.
                $t->string($column, 32)->nullable()->after('company_id');
            });

            $now = now();
            $companies = DB::table($table)->distinct()->pluck('company_id');

            foreach ($companies as $companyId) {
                $ids = DB::table($table)->where('company_id', $companyId)
                    ->orderBy('created_at')->orderBy('id')->pluck('id');

                foreach ($ids->values() as $i => $id) {
                    DB::table($table)->where('id', $id)
                        ->update([$column => $prefix.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT)]);
                }

                DB::table('number_sequences')->updateOrInsert(
                    ['company_id' => $companyId, 'factory_id' => null, 'document_type' => $series, 'period_key' => 'ALL'],
                    [
                        'id' => (string) Str::ulid(),
                        'format' => $prefix.'{SEQ}',
                        'reset_policy' => 'NEVER',
                        'padding' => 5,
                        'current_value' => $ids->count(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }

            Schema::table($table, function (Blueprint $t) use ($table, $column): void {
                $t->unique(['company_id', $column], "{$table}_{$column}_unique");
            });
        }
    }

    public function down(): void
    {
        foreach (self::NUMBERED as $table => [$column, $series]) {
            Schema::table($table, function (Blueprint $t) use ($table, $column): void {
                $t->dropUnique("{$table}_{$column}_unique");
                $t->dropColumn($column);
            });
            DB::table('number_sequences')->where('document_type', $series)->delete();
        }
    }
};
