<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Export Statement (AnnotechERP 35, from Norban's "MD Sir Export Status",
 * user 2026-11-21): one row per export invoice, kept as Order Summary's
 * sheet — groups and columns as data (Column Setup), each statement's cells
 * in `values` (jsonb) with the version a save checks, every changed cell
 * (es_changes) and the column history (es_column_events). An edit of a saved
 * statement is held in es_edit_requests until the Approval Center approves it
 * (Norban: the department head), and only then written onto the statement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('es_groups', function (Blueprint $table): void {
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

        Schema::create('es_columns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('group_id')->constrained('es_groups')->restrictOnDelete();
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
            $table->boolean('bold')->default(false);
            $table->string('kpi', 8)->nullable();
            $table->string('pair_reduce', 8)->nullable();
            $table->json('role_access')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('hidden')->default(false);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'key']);
            $table->unique(['company_id', 'label']);
        });

        Schema::create('es_statements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedInteger('serial_no');
            // CREATED | PENDING_APPROVAL | APPROVED — Norban's statuses; a rejected edit leaves it as it was.
            $table->string('status', 20)->default('CREATED');
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->jsonb('values')->default('{}');
            $table->unsignedInteger('version')->default(1);
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'serial_no']);
            $table->index(['company_id', 'status']);
        });
        DB::statement('CREATE INDEX es_statements_values_gin ON es_statements USING gin ("values")');

        Schema::create('es_changes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->ulid('statement_id');
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('kind', 12); // CREATE | EDIT | DELETE | RECALC
            $table->string('column_key', 64)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->ulid('batch');
            $table->foreignUlid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->index(['company_id', 'changed_at']);
            $table->index(['company_id', 'statement_id']);
        });

        Schema::create('es_column_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('column_key', 64);
            $table->string('action', 16);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->unsignedInteger('recalculated')->nullable();
            $table->foreignUlid('by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('at');
            $table->index(['company_id', 'at']);
        });

        Schema::create('es_edit_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('statement_id')->constrained('es_statements')->cascadeOnDelete();
            // The changed cells only: {key: [current, new]}; written onto the statement when approved.
            $table->json('diff');
            $table->string('source', 12)->default('EDIT'); // EDIT | IMPORT
            // PENDING | APPROVED | REJECTED | CANCELLED
            $table->string('status', 12)->default('PENDING');
            $table->string('approval_status', 20)->nullable();
            $table->foreignUlid('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['statement_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('es_edit_requests');
        Schema::dropIfExists('es_column_events');
        Schema::dropIfExists('es_changes');
        DB::statement('DROP INDEX IF EXISTS es_statements_values_gin');
        Schema::dropIfExists('es_statements');
        Schema::dropIfExists('es_columns');
        Schema::dropIfExists('es_groups');
    }
};
