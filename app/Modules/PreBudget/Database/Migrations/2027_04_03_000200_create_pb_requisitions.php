<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-Budget → Requisition (AnnotechERP 33/34, plan 2026-11-19): one
 * requisition table for Knit (kg) and Woven (yds) — Norban kept two identical
 * tables — with its columns and formulas as data (Column Setup, like Order
 * Summary): groups, columns (each may apply to Knit, Woven or both), the
 * requisition rows (values in jsonb; a Draft until Completed, its serial
 * number given on Complete), every changed cell, and the column history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pb_groups', function (Blueprint $table): void {
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

        Schema::create('pb_columns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('group_id')->constrained('pb_groups')->restrictOnDelete();
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
            $table->string('applies_to', 8)->nullable(); // KNIT | WOVEN | null = both
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

        Schema::create('pb_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedInteger('serial_no')->nullable(); // given on Complete
            $table->string('entry_type', 8); // KNIT | WOVEN
            $table->string('status', 12)->default('DRAFT'); // DRAFT | COMPLETE
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->jsonb('values');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('completed_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'serial_no']);
            $table->index(['company_id', 'status', 'entry_type']);
            $table->index(['company_id', 'buyer_id']);
        });
        DB::statement('CREATE INDEX pb_requisitions_values_gin ON pb_requisitions USING gin ("values")');

        Schema::create('pb_changes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('subject', 16); // REQUISITION (budgets join in step 3)
            $table->ulid('subject_id');
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('kind', 12); // CREATE | EDIT | COMPLETE | DELETE | RECALC
            $table->string('column_key', 64)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->ulid('batch');
            $table->foreignUlid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->index(['company_id', 'changed_at']);
            $table->index(['company_id', 'subject', 'subject_id']);
        });

        Schema::create('pb_column_events', function (Blueprint $table): void {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('pb_column_events');
        Schema::dropIfExists('pb_changes');
        Schema::dropIfExists('pb_requisitions');
        Schema::dropIfExists('pb_columns');
        Schema::dropIfExists('pb_groups');
    }
};
