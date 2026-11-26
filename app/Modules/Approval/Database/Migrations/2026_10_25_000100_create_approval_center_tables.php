<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approval Center (AnnotechERP 04) and the Approval Workflow Designer (02/03):
 * request types and their routing, hand-written requests, the "Request To"
 * first level, return for revision, and the designer's own fields on the
 * existing chain tables.
 *
 * Existing rows keep working unchanged: a step defaults to approval level 1 and
 * active, and a request without a Request To runs on its workflow alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_workflows', function (Blueprint $table): void {
            $table->string('type_code', 16)->nullable()->after('name');
            $table->string('short_name', 32)->nullable();
            $table->string('document_type')->nullable();
            $table->string('workflow_type', 32)->nullable();
            $table->string('page_url')->nullable();

            $table->unique(['company_id', 'type_code']);
        });

        // Chains made before the designer get the codes it shows: AT-001, AT-002 …
        // per company, oldest first.
        foreach (DB::table('approval_workflows')->orderBy('company_id')->orderBy('created_at')->orderBy('id')->get(['id', 'company_id'])->groupBy('company_id') as $rows) {
            foreach ($rows->values() as $i => $row) {
                DB::table('approval_workflows')->where('id', $row->id)->update(['type_code' => sprintf('AT-%03d', $i + 1)]);
            }
        }

        Schema::table('approval_rules', function (Blueprint $table): void {
            $table->string('task_message')->nullable();
            $table->string('role_flag')->nullable();
            $table->string('next_action')->nullable();
            // 0 = a working step (raise, issue, receive) that asks nobody for
            // a decision; 1 and up = an approval level.
            $table->unsignedSmallInteger('level')->default(1);
            $table->boolean('requires_signature')->default(false);
            $table->boolean('can_repeat')->default(false);
            $table->boolean('can_cancel')->default(false);
            $table->boolean('can_resend')->default(false);
            $table->boolean('active')->default(true);
        });

        // Action Assignment: the named people a step lands on.
        Schema::create('approval_rule_users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('rule_id')->constrained('approval_rules')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['rule_id', 'user_id']);
        });

        Schema::create('approval_request_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 48);
            $table->string('name');
            $table->string('module');
            $table->string('default_priority', 8)->default('MEDIUM');
            $table->unsignedSmallInteger('due_days')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUlid('workflow_id')->nullable()->constrained('approval_workflows')->nullOnDelete();
            $table->foreignUlid('escalate_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('escalate_after_hours')->default(24);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('approval_tickets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('request_no', 32)->nullable();
            $table->foreignUlid('request_type_id')->constrained('approval_request_types')->restrictOnDelete();
            $table->string('priority', 8)->default('MEDIUM');
            $table->string('title')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('order_no')->nullable();
            $table->string('style_no')->nullable();
            $table->string('po_no')->nullable();
            $table->decimal('quantity', 18, 3)->nullable();
            $table->string('unit', 8)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->foreignUlid('request_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 12)->default('DRAFT');
            $table->string('approval_status', 16)->nullable();
            $table->ulid('approval_request_id')->nullable();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'request_no']);
            $table->index(['company_id', 'status']);
        });

        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->ulid('workflow_id')->nullable()->change();
            $table->foreignUlid('request_type_id')->nullable()->constrained('approval_request_types')->nullOnDelete();
            $table->string('priority', 8)->nullable();
            $table->dateTime('due_at', 3)->nullable();
            $table->foreignUlid('request_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('escalated_at', 3)->nullable();
        });

        Schema::table('approval_actions', function (Blueprint $table): void {
            $table->string('previous_status', 16)->nullable();
            $table->string('new_status', 16)->nullable();
            $table->unsignedSmallInteger('level')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('approval_actions', function (Blueprint $table): void {
            $table->dropColumn(['previous_status', 'new_status', 'level']);
        });
        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('request_type_id');
            $table->dropConstrainedForeignId('request_to_user_id');
            $table->dropColumn(['priority', 'due_at', 'escalated_at']);
        });
        Schema::dropIfExists('approval_tickets');
        Schema::dropIfExists('approval_request_types');
        Schema::dropIfExists('approval_rule_users');
        Schema::table('approval_rules', function (Blueprint $table): void {
            $table->dropColumn(['task_message', 'role_flag', 'next_action', 'level', 'requires_signature', 'can_repeat', 'can_cancel', 'can_resend', 'active']);
        });
        Schema::table('approval_workflows', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'type_code']);
            $table->dropColumn(['type_code', 'short_name', 'document_type', 'workflow_type', 'page_url']);
        });
    }
};
