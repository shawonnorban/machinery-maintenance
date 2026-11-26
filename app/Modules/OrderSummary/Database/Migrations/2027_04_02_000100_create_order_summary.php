<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Order Summary (AnnotechERP 32, from the user's Norban ERP): a configurable
 * order-tracking sheet. Its columns are data, not code — each company has its
 * groups and columns (Norban's 161 to start, then whatever Column Setup adds),
 * with a formula where a column is worked out from others. An order row keeps
 * every column's value in one `values` document, so a new column needs no
 * schema change; `buyer_id` sits beside it because who may see a row depends
 * on it.
 *
 * `os_changes` is every cell that changed (the audit log, and the edits the
 * mail digest sends); `os_column_events` the history of the columns and their
 * formulas. The merchandising teams say who sees which buyers: a department
 * head every team of their department, a team head their team's
 * merchandisers' buyers, a merchandiser their own (Buyer Account Permission).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('os_groups', function (Blueprint $table): void {
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

        Schema::create('os_columns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('group_id')->constrained('os_groups')->restrictOnDelete();
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
            $table->boolean('required')->default(false);
            $table->text('formula')->nullable();
            $table->json('color_rules')->nullable();
            $table->string('kpi', 8)->nullable(); // SUM | AVG — a summary card over the filtered rows
            $table->json('role_access')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('hidden')->default(false);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'key']);
            $table->unique(['company_id', 'label']);
        });

        Schema::create('os_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedInteger('serial_no');
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->jsonb('values');
            $table->foreignUlid('artwork_file_id')->nullable()->constrained('file_attachments')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'serial_no']);
            $table->index(['company_id', 'buyer_id']);
        });
        DB::statement('CREATE INDEX os_orders_values_gin ON os_orders USING gin ("values")');

        Schema::create('os_changes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('os_orders')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('kind', 12); // CREATE | EDIT | DELETE | RESTORE | RECALC
            $table->string('column_key', 64)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->ulid('batch');
            $table->foreignUlid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamp('mailed_at')->nullable();
            $table->index(['company_id', 'changed_at']);
            $table->index(['company_id', 'order_id']);
            $table->index(['company_id', 'kind', 'mailed_at']);
        });

        Schema::create('os_column_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('column_key', 64);
            $table->string('action', 16); // ADD | EDIT | FORMULA | HIDE | SHOW | MOVE | DELETE
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->unsignedInteger('recalculated')->nullable();
            $table->foreignUlid('by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('at');
            $table->index(['company_id', 'at']);
        });

        Schema::create('os_department_heads', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['department_id', 'user_id']);
        });

        Schema::create('os_teams', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUlid('team_head_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('os_team_members', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('team_id')->constrained('os_teams')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('os_mail_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->json('times')->nullable();
            $table->foreignUlid('mail_service_id')->nullable()->constrained('mail_services')->nullOnDelete();
            $table->timestamp('last_sent_at')->nullable();
            $table->string('last_slot', 32)->nullable();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['os_mail_settings', 'os_team_members', 'os_teams', 'os_department_heads', 'os_column_events', 'os_changes', 'os_orders', 'os_columns', 'os_groups'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
