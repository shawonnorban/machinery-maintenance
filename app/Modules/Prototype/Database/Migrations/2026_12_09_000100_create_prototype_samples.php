<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Prototype Desk → Sample Planning & Development (AnnotechERP 07/03): the
 * merchandiser's sample requisition and the decision each approval level
 * takes on it, the sample the sample manager's approval mints, one
 * development per version of a sample with its fourteen-point checklist,
 * and the revisions that move a sample to its next version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proto_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 24);
            $table->date('req_date');
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->foreignUlid('buyer_office_id')->nullable()->constrained('buyer_offices')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('style_id')->constrained('merch_styles')->restrictOnDelete();
            $table->string('season', 64)->nullable();
            $table->string('product_category', 120)->nullable();
            $table->foreignUlid('sample_type_id')->constrained('sample_types')->restrictOnDelete();
            $table->string('sample_stage', 20);
            $table->unsignedInteger('qty');
            $table->date('required_date');
            $table->string('priority', 10)->default('NORMAL');
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->string('attachment', 500)->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->string('pending_level', 24)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'req_no']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('proto_requisition_decisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('requisition_id')->constrained('proto_requisitions')->cascadeOnDelete();
            $table->string('level', 24);
            $table->string('decision', 10);
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at');
            $table->text('comments')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('attachment', 500)->nullable();
            $table->timestamps();
            $table->index('requisition_id');
        });

        Schema::create('proto_samples', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('sample_no', 24);
            $table->foreignUlid('requisition_id')->constrained('proto_requisitions')->restrictOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('phase', 16)->default('REQUISITION');
            $table->string('state', 16)->default('APPROVED');
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'sample_no']);
            $table->unique('requisition_id');
        });

        Schema::table('proto_requisitions', function (Blueprint $table): void {
            $table->foreignUlid('sample_id')->nullable()->after('pending_level')->constrained('proto_samples')->nullOnDelete();
        });

        Schema::create('proto_developments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('dev_no', 24);
            $table->foreignUlid('sample_id')->constrained('proto_samples')->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('status', 16)->default('PENDING');
            $table->decimal('progress', 5, 1)->default(0);
            $table->date('dev_date');
            $table->date('start_date')->nullable();
            $table->date('expected_completion')->nullable();
            $table->date('actual_completion')->nullable();
            $table->foreignUlid('pattern_master')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('sample_maker')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('technician')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('supervisor')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delay_reason', 500)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'dev_no']);
            $table->unique(['sample_id', 'version']);
        });

        Schema::create('proto_development_tasks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('development_id')->constrained('proto_developments')->cascadeOnDelete();
            $table->string('task', 16);
            $table->string('status', 16)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->unique(['development_id', 'task']);
        });

        Schema::create('proto_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('rev_no', 24);
            $table->foreignUlid('sample_id')->constrained('proto_samples')->restrictOnDelete();
            $table->unsignedSmallInteger('previous_version');
            $table->unsignedSmallInteger('new_version');
            $table->date('revision_date');
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('OPEN');
            $table->string('reason', 500);
            $table->ulid('source_approval_id')->nullable();
            $table->ulid('source_feedback_id')->nullable();
            $table->text('buyer_comment')->nullable();
            $table->text('internal_comment')->nullable();
            $table->text('changed_measurement')->nullable();
            $table->text('changed_fabric')->nullable();
            $table->text('changed_color')->nullable();
            $table->text('changed_accessories')->nullable();
            $table->text('changed_construction')->nullable();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('attachment', 500)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'rev_no']);
            $table->unique(['sample_id', 'new_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proto_revisions');
        Schema::dropIfExists('proto_development_tasks');
        Schema::dropIfExists('proto_developments');
        Schema::table('proto_requisitions', fn (Blueprint $table) => $table->dropConstrainedForeignId('sample_id'));
        Schema::dropIfExists('proto_samples');
        Schema::dropIfExists('proto_requisition_decisions');
        Schema::dropIfExists('proto_requisitions');
    }
};
