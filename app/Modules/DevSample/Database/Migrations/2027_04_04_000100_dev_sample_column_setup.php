<?php

declare(strict_types=1);

use App\Modules\DevSample\Support\DsLegacy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dev Sample Status → Column Setup (user 2026-11-21: "like Order Summary"):
 * the sample sheet's groups and columns kept as data — Order Summary's
 * Column Setup on the samples — every changed cell (ds_changes) and the
 * column history (ds_column_events). A sample's cells move into `values`
 * (jsonb, as Order Summary's orders), with the version a save checks; the
 * typed columns stay as they were (read by nothing now, kept so nothing is
 * lost), each existing sample's cells copied into `values` here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ds_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('tone', 16)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('ds_columns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('group_id')->constrained('ds_groups')->restrictOnDelete();
            $table->string('key', 64);
            $table->string('label', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type', 16)->default('text');
            $table->string('source', 64)->nullable();
            $table->boolean('multiple')->default(false);
            $table->string('prefix', 12)->nullable();
            $table->string('suffix', 12)->nullable();
            $table->unsignedTinyInteger('decimals')->nullable();
            $table->unsignedSmallInteger('width')->default(130);
            $table->boolean('vertical')->default(false);
            $table->boolean('frozen')->default(false);
            $table->boolean('required')->default(false);
            $table->text('formula')->nullable();
            $table->json('color_rules')->nullable();
            $table->string('text_tone', 16)->nullable();
            $table->string('kpi', 8)->nullable();
            $table->json('role_access')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('hidden')->default(false);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'key']);
            $table->unique(['company_id', 'label']);
        });

        Schema::create('ds_changes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->ulid('sample_id');
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('kind', 12); // CREATE | EDIT | DELETE | RECALC
            $table->string('column_key', 64)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->ulid('batch');
            $table->foreignUlid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->index(['company_id', 'changed_at']);
            $table->index(['company_id', 'sample_id']);
        });

        Schema::create('ds_column_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('column_key', 64);
            $table->string('action', 16); // ADD | EDIT | FORMULA | HIDE | SHOW | FREEZE | DELETE
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->unsignedInteger('recalculated')->nullable();
            $table->foreignUlid('by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('at');
            $table->index(['company_id', 'at']);
        });

        Schema::table('ds_samples', function (Blueprint $table): void {
            $table->jsonb('values')->default('{}');
            $table->unsignedInteger('version')->default(1);
        });
        DB::statement('CREATE INDEX ds_samples_values_gin ON ds_samples USING gin ("values")');

        // Every sample's typed cells into its values (the sheet's keys, lists as their labels).
        foreach (DB::table('ds_samples')->orderBy('id')->get() as $row) {
            DB::table('ds_samples')->where('id', $row->id)->update(['values' => json_encode(DsLegacy::values($row), JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ds_samples_values_gin');
        Schema::table('ds_samples', function (Blueprint $table): void {
            $table->dropColumn(['values', 'version']);
        });
        Schema::dropIfExists('ds_column_events');
        Schema::dropIfExists('ds_changes');
        Schema::dropIfExists('ds_columns');
        Schema::dropIfExists('ds_groups');
    }
};
